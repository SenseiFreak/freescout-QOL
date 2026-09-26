<?php

namespace FreescoutQOL\Calendar;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Direct provider access: external calendars remain the source of truth. */
class CalendarProvider
{
    public static function settings($provider)
    {
        abort_unless(in_array($provider, ['google', 'microsoft'], true), 404);
        $raw = DB::table('user_preferences')->where('user_id', 0)->where('key', 'qol_calendar_'.$provider)->value('value');
        return $raw ? json_decode(Crypt::decryptString($raw), true) : ['client_id' => '', 'client_secret' => ''];
    }

    public static function endpoints($provider)
    {
        return $provider === 'google' ? [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/calendar.calendarlist.readonly https://www.googleapis.com/auth/calendar.events',
            'api' => 'https://www.googleapis.com/calendar/v3/',
        ] : [
            'authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'scope' => 'offline_access https://graph.microsoft.com/Calendars.ReadWrite',
            'api' => 'https://graph.microsoft.com/v1.0/',
        ];
    }

    protected function http($method, $url, $options)
    {
        $client = new Client(['timeout' => 20, 'connect_timeout' => 5, 'allow_redirects' => false, 'http_errors' => false]);
        $response = $client->request($method, $url, $options);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            // Do not expose tokens, provider responses, or client secrets in errors.
            throw new \RuntimeException('Calendar provider rejected the request (HTTP '.$response->getStatusCode().'). Reconnect or check calendar permissions.');
        }
        return json_decode((string) $response->getBody(), true) ?: [];
    }

    public function exchange($provider, $fields)
    {
        $settings = self::settings($provider);
        return $this->http('POST', self::endpoints($provider)['token'], ['form_params' => array_merge($settings, $fields)]);
    }

    public function saveTokens($provider, $userId, $tokens)
    {
        if (empty($tokens['access_token'])) {
            throw new \RuntimeException('Calendar authorization did not return an access token.');
        }
        $tokens['expires_at'] = time() + ($tokens['expires_in'] ?? 3600);
        DB::table('qol_calendar_connections')->updateOrInsert(['user_id' => $userId, 'provider' => $provider], [
            'tokens' => Crypt::encryptString(json_encode($tokens)), 'updated_at' => gmdate('Y-m-d H:i:s'), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function api($provider, $userId, $method, $path, $options = [])
    {
        $row = DB::table('qol_calendar_connections')->where('user_id', $userId)->where('provider', $provider)->first();
        if (!$row) {
            throw new \RuntimeException('Connect this calendar account first.');
        }
        $tokens = json_decode(Crypt::decryptString($row->tokens), true);
        if ($tokens['expires_at'] <= time() + 60) {
            if (empty($tokens['refresh_token'])) {
                throw new \RuntimeException('Calendar authorization expired. Please reconnect.');
            }
            $new = $this->exchange($provider, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]);
            $tokens = array_merge($tokens, $new);
            $this->saveTokens($provider, $userId, $tokens);
        }
        $options['headers']['Authorization'] = 'Bearer '.$tokens['access_token'];
        $options['headers']['Accept'] = 'application/json';
        if ($provider === 'microsoft') {
            $options['headers']['Prefer'] = 'outlook.timezone="UTC"';
        }
        return $this->http($method, self::endpoints($provider)['api'].$path, $options);
    }

    public function calendars($provider, $userId)
    {
        $path = $provider === 'google' ? 'users/me/calendarList' : 'me/calendars';
        $items = $this->pages($provider, $userId, $path, []);
        return array_map(function ($item) use ($provider) {
            return [
                'id' => $item['id'], 'name' => $item['summary'] ?? $item['name'] ?? 'Calendar',
                'writable' => $provider === 'google' ? in_array($item['accessRole'] ?? '', ['owner', 'writer']) : !empty($item['canEdit']),
            ];
        }, $items);
    }

    protected function pages($provider, $userId, $path, $query)
    {
        $items = [];
        for ($page = 0; $page < 100; $page++) {
            $result = $this->api($provider, $userId, 'GET', $path, ['query' => $query]);
            $items = array_merge($items, $result[$provider === 'google' ? 'items' : 'value'] ?? []);
            if ($provider === 'google' && !empty($result['nextPageToken'])) {
                $query['pageToken'] = $result['nextPageToken'];
            } elseif ($provider === 'microsoft' && !empty($result['@odata.nextLink'])) {
                $base = self::endpoints($provider)['api'];
                if (strpos($result['@odata.nextLink'], $base) !== 0) {
                    throw new \RuntimeException('Unexpected calendar pagination URL.');
                }
                $path = substr($result['@odata.nextLink'], strlen($base));
                $query = [];
            } else {
                return $items;
            }
        }
        throw new \RuntimeException('Too many events. Select a smaller date range.');
    }

    public function events($provider, $userId, $calendar, $start, $end)
    {
        $path = $provider === 'google' ? 'calendars/'.rawurlencode($calendar).'/events' : 'me/calendars/'.rawurlencode($calendar).'/calendarView';
        $query = $provider === 'google'
            ? ['timeMin' => $start, 'timeMax' => $end, 'singleEvents' => 'true', 'maxResults' => 2500]
            : ['startDateTime' => $start, 'endDateTime' => $end, '$top' => 1000];
        $items = $this->pages($provider, $userId, $path, $query);
        $events = [];
        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'cancelled' || !empty($item['isCancelled'])) {
                continue;
            }
            $allDay = $provider === 'google' ? isset($item['start']['date']) : !empty($item['isAllDay']);
            $start = $item['start']['dateTime'] ?? $item['start']['date'] ?? null;
            $end = $item['end']['dateTime'] ?? $item['end']['date'] ?? null;
            if (!$start || !$end) { continue; }
            if ($provider === 'microsoft') {
                $start = $allDay ? substr($start, 0, 10) : $start.'Z';
                $end = $allDay ? substr($end, 0, 10) : $end.'Z';
            }
            $events[] = ['id' => $item['id'], 'title' => $item['summary'] ?? $item['subject'] ?? '(Untitled)',
                'start' => $start, 'end' => $end, 'allDay' => $allDay, 'source' => $provider,
                'url' => $item['htmlLink'] ?? $item['webLink'] ?? null];
        }
        return $events;
    }

    public function create($provider, $userId, $calendar, $event)
    {
        if ($provider === 'google') {
            $body = ['summary' => $event['title'], 'description' => $event['description'],
                'start' => ['dateTime' => $event['start'], 'timeZone' => $event['timezone']],
                'end' => ['dateTime' => $event['end'], 'timeZone' => $event['timezone']]];
            $path = 'calendars/'.rawurlencode($calendar).'/events';
        } else {
            $body = ['subject' => $event['title'], 'body' => ['contentType' => 'text', 'content' => $event['description']],
                'start' => ['dateTime' => gmdate('Y-m-d\TH:i:s', strtotime($event['start'])), 'timeZone' => 'UTC'],
                'end' => ['dateTime' => gmdate('Y-m-d\TH:i:s', strtotime($event['end'])), 'timeZone' => 'UTC']];
            $path = 'me/calendars/'.rawurlencode($calendar).'/events';
        }
        return $this->api($provider, $userId, 'POST', $path, ['json' => $body]);
    }
}
