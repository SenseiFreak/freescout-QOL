<?php

namespace Modules\Qol\Http\Controllers;

use Modules\Qol\Calendar\CalendarProvider;
use Modules\Qol\Calendar\TicketCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CalendarController
{
    public function importTicketEvents(Request $request)
    {
        $request->validate(['ticket' => 'required|integer|min:1']);
        $ticket = $this->ticket($request->input('ticket'));
        abort_unless(TicketCalendar::ready(), 503, 'Run php artisan migrate to enable ticket calendar history.');
        $default = self::defaultCalendar();
        abort_unless($default['source'] === 'google' && !empty($default['calendar']), 422, 'Choose your Google calendar as the default in QoL settings first.');
        return $this->remote(function () use ($ticket, $default) {
            $items = (new CalendarProvider())->findGoogleTicketEvents(Auth::id(), $default['calendar'], route('conversations.view', ['id' => $ticket->id]));
            $notes = [];
            foreach ($items as $item) {
                // Previous module versions created timed, non-recurring events.
                if (empty($item['start']['dateTime']) || empty($item['end']['dateTime']) || !empty($item['recurrence'])) { continue; }
                $key = TicketCalendar::remoteKey('google', $default['calendar'], $item['id']);
                $note = DB::transaction(function () use ($ticket, $default, $item, $key) {
                    // Serialize imports for this ticket to avoid duplicate audit notes.
                    \App\Conversation::where('id', $ticket->id)->lockForUpdate()->first();
                    if (DB::table('qol_calendar_events')->where('remote_key', $key)->exists()) { return null; }
                    $utc = new \DateTimeZone('UTC');
                    $zone = $item['start']['timeZone'] ?? 'UTC';
                    if (!in_array($zone, \DateTimeZone::listIdentifiers(), true)) { $zone = 'UTC'; }
                    $id = DB::table('qol_calendar_events')->insertGetId([
                        'user_id' => Auth::id(), 'conversation_id' => $ticket->id, 'source' => 'google', 'calendar_id' => $default['calendar'],
                        'remote_id' => $item['id'], 'remote_key' => $key, 'remote_url' => $item['htmlLink'] ?? null,
                        'title' => mb_substr($item['summary'] ?? __('Calendar event'), 0, 255),
                        'description' => null, 'timezone' => $zone, 'imported' => true,
                        'starts_at' => (new \DateTimeImmutable($item['start']['dateTime']))->setTimezone($utc)->format('Y-m-d H:i:s'),
                        'ends_at' => (new \DateTimeImmutable($item['end']['dateTime']))->setTimezone($utc)->format('Y-m-d H:i:s'),
                        'creator_name' => mb_substr($item['creator']['displayName'] ?? $item['creator']['email'] ?? __('Unknown (Google)'), 0, 255),
                        'created_at' => (new \DateTimeImmutable($item['created'] ?? 'now'))->setTimezone($utc)->format('Y-m-d H:i:s'),
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    return TicketCalendar::note($ticket, $id);
                });
                if ($note) { $notes[] = $note; }
            }
            return ['ok' => true, 'notes' => $notes, 'message' => __('Linked :count earlier Google events.', ['count' => count($notes)])];
        });
    }

    public function ticketEvents(Request $request)
    {
        $request->validate(['ticket' => 'required|integer|min:1']);
        $ticket = $this->ticket($request->input('ticket'));
        abort_unless(TicketCalendar::ready(), 503, 'Run php artisan migrate to enable ticket calendar history.');
        return response()->json(TicketCalendar::rows($ticket));
    }

    public function ticketEvent(Request $request)
    {
        $request->validate(['ticket' => 'required|integer|min:1']);
        $ticket = $this->ticket($request->input('ticket'));
        return response()->json([
            'ticket' => $ticket->id,
            'title' => '#'.$ticket->number.' '.$ticket->subject,
            'calendars' => self::defaultCalendarOptions(),
        ]);
    }

    public static function defaultCalendar()
    {
        $raw = DB::table('user_preferences')->where('user_id', Auth::id())->where('key', 'qol_default_calendar')->value('value');
        $saved = $raw ? json_decode($raw, true) : null;
        return is_array($saved) && in_array($saved['source'] ?? '', ['local', 'google', 'microsoft'], true)
            ? $saved : ['source' => 'local', 'calendar' => ''];
    }

    public static function defaultCalendarOptions()
    {
        $options = [['source' => 'local', 'calendar' => '', 'name' => __('FreeScout • Personal')]];
        $errors = [];
        $providers = DB::table('qol_calendar_connections')->where('user_id', Auth::id())->pluck('provider');
        foreach ($providers as $provider) {
            try {
                foreach ((new CalendarProvider())->calendars($provider, Auth::id()) as $calendar) {
                    $options[] = ['source' => $provider, 'calendar' => $calendar['id'], 'writable' => $calendar['writable'],
                        'name' => ($provider === 'google' ? 'Google' : 'Microsoft 365').' • '.$calendar['name'].($calendar['writable'] ? '' : ' ('.__('read only').')')];
                }
            } catch (\Exception $e) {
                $errors[] = __('Could not load :provider calendars. Reconnect the account from Calendar and try again.', ['provider' => $provider]);
            }
        }
        $saved = self::defaultCalendar();
        $found = false;
        foreach ($options as $option) {
            if ($option['source'] === $saved['source'] && $option['calendar'] === ($saved['calendar'] ?? '')) { $found = true; }
        }
        // Keep the saved choice visible during an outage; never silently replace it.
        if (!$found) {
            $options[] = ['source' => $saved['source'], 'calendar' => $saved['calendar'] ?? '', 'writable' => false, 'name' => __('Saved calendar (currently unavailable)')];
        }
        return ['options' => $options, 'errors' => $errors, 'saved' => $saved];
    }

    public function saveDefault(Request $request)
    {
        $request->validate(['default_calendar' => 'required|string|max:4096']);
        $choice = json_decode($request->input('default_calendar'), true);
        abort_unless(is_array($choice) && isset($choice['source'], $choice['calendar'])
            && in_array($choice['source'], ['local', 'google', 'microsoft'], true) && is_string($choice['calendar']), 422);
        if ($choice['source'] === 'local') {
            $choice['calendar'] = '';
        } else {
            try {
                $calendars = (new CalendarProvider())->calendars($choice['source'], Auth::id());
            } catch (\Exception $e) {
                return redirect()->route('qol.settings')->with('flash_error', __('Could not verify the calendar. Your previous default has been kept.'));
            }
            $found = false;
            foreach ($calendars as $calendar) { if ($calendar['id'] === $choice['calendar']) { $found = true; } }
            abort_unless($found, 422, 'Choose a calendar from your connected account.');
        }
        DB::table('user_preferences')->updateOrInsert(['user_id' => Auth::id(), 'key' => 'qol_default_calendar'],
            ['value' => json_encode(['source' => $choice['source'], 'calendar' => $choice['calendar']]), 'updated_at' => now(), 'created_at' => now()]);
        return redirect()->route('qol.settings')->with('flash_success', __('Default calendar saved.'));
    }

    public function index(Request $request)
    {
        $ticket = $request->filled('ticket') ? $this->ticket($request->input('ticket')) : null;
        $connections = DB::table('qol_calendar_connections')->where('user_id', Auth::id())->pluck('provider')->all();
        $defaultCalendar = self::defaultCalendar();
        return view('qol::calendar.index', compact('ticket', 'connections', 'defaultCalendar'));
    }

    protected function ticket($id)
    {
        abort_unless(ctype_digit((string) $id), 422);
        $ticket = \App\Conversation::findOrFail($id);
        abort_unless(Auth::user()->can('view', $ticket), 403);
        return $ticket;
    }

    public function settings(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        if ($request->isMethod('post')) {
            // Avoid Laravel's automatic old-input flashing for client secrets.
            $validator = \Validator::make($request->only('provider', 'client_id', 'client_secret'), ['provider' => 'required|in:google,microsoft', 'client_id' => 'required|string|max:500', 'client_secret' => 'nullable|string|max:1000']);
            if ($validator->fails()) { return redirect()->route('qol.calendar.settings')->withErrors($validator); }
            $provider = $request->input('provider');
            $settings = CalendarProvider::settings($provider);
            $settings['client_id'] = $request->input('client_id');
            if ($request->filled('client_secret')) { $settings['client_secret'] = $request->input('client_secret'); }
            abort_if(empty($settings['client_secret']), 422, 'A client secret is required.');
            DB::table('user_preferences')->updateOrInsert(['user_id' => 0, 'key' => 'qol_calendar_'.$provider], [
                'value' => Crypt::encryptString(json_encode($settings)), 'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            return redirect()->route('qol.calendar.settings')->with('flash_success', __('Calendar settings saved.'));
        }
        $clients = [];
        foreach (['google', 'microsoft'] as $provider) {
            $settings = CalendarProvider::settings($provider);
            $clients[$provider] = $settings['client_id'];
        }
        return view('qol::calendar.settings', compact('clients'));
    }

    public function connect(Request $request, $provider)
    {
        $settings = CalendarProvider::settings($provider);
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            $message = __('Save both the client ID and client secret in Calendar setup before connecting.');
            return $request->expectsJson() ? response()->json(['message' => $message], 422)
                : redirect()->route('qol.calendar')->with('flash_error', $message);
        }
        $state = bin2hex(random_bytes(32));
        $verifier = bin2hex(random_bytes(32));
        $request->session()->put('qol_calendar_oauth', ['state' => $state, 'verifier' => $verifier, 'provider' => $provider, 'user_id' => Auth::id(), 'expires' => time() + 600]);
        $params = ['client_id' => $settings['client_id'], 'redirect_uri' => route('qol.calendar.callback', ['provider' => $provider]),
            'response_type' => 'code', 'scope' => CalendarProvider::endpoints($provider)['scope'], 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'];
        if ($provider === 'google') { $params['access_type'] = 'offline'; $params['prompt'] = 'consent'; }
        $url = CalendarProvider::endpoints($provider)['authorize'].'?'.http_build_query($params);
        // Fetch must not follow an OAuth redirect cross-origin. Let the browser
        // navigate only after the session-bound state has been saved by middleware.
        return $request->expectsJson() ? response()->json(['redirect_url' => $url]) : redirect()->away($url);
    }

    public function callback(Request $request, $provider)
    {
        $state = $request->session()->pull('qol_calendar_oauth');
        abort_unless($state && is_string($request->input('state')) && hash_equals($state['state'], $request->input('state'))
            && $state['provider'] === $provider && $state['user_id'] === Auth::id() && $state['expires'] >= time(), 403);
        if (!$request->filled('code') || $request->filled('error')) {
            return redirect()->route('qol.calendar')->with('flash_error', __('Calendar connection was not authorized.'));
        }
        try {
            $service = new CalendarProvider();
            $tokens = $service->exchange($provider, ['grant_type' => 'authorization_code', 'code' => $request->input('code'),
                'redirect_uri' => route('qol.calendar.callback', ['provider' => $provider]), 'code_verifier' => $state['verifier']]);
            $service->saveTokens($provider, Auth::id(), $tokens);
            return redirect()->route('qol.calendar')->with('flash_success', __('Calendar connected.'));
        } catch (\Exception $e) {
            return redirect()->route('qol.calendar')->with('flash_error', __('Could not connect. Check the app credentials and try again.'));
        }
    }

    public function disconnect(Request $request)
    {
        $request->validate(['provider' => 'required|in:google,microsoft']);
        DB::table('qol_calendar_connections')->where('user_id', Auth::id())->where('provider', $request->input('provider'))->delete();
        if ($request->expectsJson()) {
            $request->session()->flash('flash_success', __('Account disconnected. To revoke consent, remove the app in your provider account settings.'));
            return response()->json(['redirect_url' => route('qol.calendar')]);
        }
        return redirect()->route('qol.calendar')->with('flash_success', __('Account disconnected. To revoke consent, remove the app in your provider account settings.'));
    }

    public function calendars(Request $request)
    {
        $request->validate(['provider' => 'required|in:google,microsoft']);
        return $this->remote(function () use ($request) {
            return (new CalendarProvider())->calendars($request->input('provider'), Auth::id());
        });
    }

    protected function remote($operation)
    {
        try { return response()->json($operation()); }
        catch (\Exception $e) { return response()->json(['message' => __('Calendar request failed. Reconnect the account, check permissions, or try again shortly.')], 502); }
    }

    public function events(Request $request)
    {
        $request->validate(['source' => 'required|in:local,google,microsoft', 'calendar' => 'nullable|string|max:1024',
            'start' => 'required|date', 'end' => 'required|date|after:start']);
        $start = new \DateTimeImmutable($request->input('start'));
        $end = new \DateTimeImmutable($request->input('end'));
        abort_if($end->getTimestamp() - $start->getTimestamp() > 63 * 86400, 422);
        if ($request->input('source') !== 'local') {
            abort_unless($request->filled('calendar'), 422);
            return $this->remote(function () use ($request, $start, $end) {
                return (new CalendarProvider())->events($request->input('source'), Auth::id(), $request->input('calendar'), $start->format(DATE_RFC3339), $end->format(DATE_RFC3339));
            });
        }
        $rows = DB::table('qol_calendar_events')->where('user_id', Auth::id())
            ->when(TicketCalendar::ready(), function ($query) { $query->where('source', 'local'); })
            ->where('starts_at', '<', $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->get();
        $events = [];
        foreach ($rows as $row) {
            $ticket = $row->conversation_id ? \App\Conversation::find($row->conversation_id) : null;
            // Hide ticket details immediately if mailbox access has been removed.
            if ($row->conversation_id && (!$ticket || !Auth::user()->can('view', $ticket))) { continue; }
            $events[] = ['id' => $row->id, 'title' => $row->title, 'description' => $row->description,
                'start' => str_replace(' ', 'T', $row->starts_at).'Z', 'end' => str_replace(' ', 'T', $row->ends_at).'Z',
                'timezone' => $row->timezone, 'source' => 'local', 'ticket' => $row->conversation_id,
                'url' => $ticket ? route('conversations.view', ['id' => $ticket->id]) : null];
        }
        return response()->json($events);
    }

    public function save(Request $request)
    {
        abort_unless(TicketCalendar::ready(), 503, 'Run php artisan migrate before creating calendar events.');
        $request->validate(['id' => 'nullable|integer', 'title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000',
            'start' => 'required|date_format:Y-m-d\TH:i', 'end' => 'required|date_format:Y-m-d\TH:i|after:start',
            'timezone' => 'required|timezone', 'ticket' => 'nullable|integer|min:1', 'source' => 'required|in:local,google,microsoft',
            'calendar' => 'nullable|string|max:1024']);
        $ticket = $request->filled('ticket') ? $this->ticket($request->input('ticket')) : null;
        $zone = new \DateTimeZone($request->input('timezone'));
        $start = new \DateTimeImmutable($request->input('start'), $zone);
        $end = new \DateTimeImmutable($request->input('end'), $zone);
        // Reject DST gaps instead of silently moving the appointment.
        abort_unless($start->format('Y-m-d\TH:i') === $request->input('start') && $end->format('Y-m-d\TH:i') === $request->input('end') && $end > $start, 422, 'Invalid time in this timezone.');
        if ($request->input('source') !== 'local') {
            abort_if($request->filled('id'), 422);
            abort_unless($request->filled('calendar'), 422);
            $description = $request->input('description', '') ?: '';
            if ($ticket) { $description .= "\n\nFreeScout ticket #".$ticket->number.': '.route('conversations.view', ['id' => $ticket->id]); }
            try {
                $remote = (new CalendarProvider())->create($request->input('source'), Auth::id(), $request->input('calendar'), [
                    'title' => $request->input('title'), 'description' => $description, 'start' => $start->format(DATE_RFC3339),
                    'end' => $end->format(DATE_RFC3339), 'timezone' => $request->input('timezone')]);
            } catch (\Exception $e) {
                return response()->json(['message' => __('Calendar request failed. Check the provider before retrying.')], 502);
            }
            // The provider write has succeeded. A history failure must not invite
            // a retry that creates a duplicate appointment at the provider.
            try {
                $note = DB::transaction(function () use ($request, $ticket, $start, $end, $remote) {
                    $id = DB::table('qol_calendar_events')->insertGetId([
                        'user_id' => Auth::id(), 'conversation_id' => $ticket ? $ticket->id : null,
                        'title' => $request->input('title'), 'description' => $request->input('description'),
                        'starts_at' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                        'ends_at' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                        'timezone' => $request->input('timezone'), 'source' => $request->input('source'),
                        'calendar_id' => $request->input('calendar'), 'remote_id' => $remote['id'] ?? null,
                        'remote_key' => !empty($remote['id']) ? TicketCalendar::remoteKey($request->input('source'), $request->input('calendar'), $remote['id']) : null,
                        'remote_url' => $remote['htmlLink'] ?? $remote['webLink'] ?? null,
                        'creator_name' => Auth::user()->getFullName(), 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    return $ticket ? TicketCalendar::note($ticket, $id) : null;
                });
            } catch (\Exception $e) {
                \Log::warning('QoL calendar event created but ticket history failed', ['ticket_id' => $ticket ? $ticket->id : null]);
                return response()->json(['ok' => true, 'warning' => __('The provider event was created, but the ticket history could not be saved. Do not recreate it; ask an administrator to check the server logs.')]);
            }
            return response()->json(['ok' => true, 'ticket_note' => $note]);
        }
        $data = ['user_id' => Auth::id(), 'conversation_id' => $ticket ? $ticket->id : null, 'title' => $request->input('title'),
            'description' => $request->input('description'), 'timezone' => $request->input('timezone'),
            'starts_at' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'ends_at' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')];
        $note = DB::transaction(function () use ($request, $ticket, $data) {
            if ($request->filled('id')) {
                $row = DB::table('qol_calendar_events')->where('user_id', Auth::id())->where('id', $request->input('id'))->lockForUpdate()->first();
                abort_unless($row, 404);
                abort_unless($row->source === 'local', 422, 'Edit this event in its calendar provider.');
                if ($row->conversation_id) { $this->ticket($row->conversation_id); }
                DB::table('qol_calendar_events')->where('id', $row->id)->update($data);
                if ($ticket && (!$row->note_thread_id || (int) $row->conversation_id !== (int) $ticket->id)) { return TicketCalendar::note($ticket, $row->id); }
            } else {
                $data['created_at'] = $data['updated_at'];
                $data['creator_name'] = Auth::user()->getFullName();
                $id = DB::table('qol_calendar_events')->insertGetId($data);
                if ($ticket) { return TicketCalendar::note($ticket, $id); }
            }
            return null;
        });
        return response()->json(['ok' => true, 'ticket_note' => $note]);
    }

    public function delete(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $row = DB::table('qol_calendar_events')->where('user_id', Auth::id())->where('id', $request->input('id'))->first();
        abort_unless($row, 404);
        abort_unless(($row->source ?? 'local') === 'local', 422, 'Delete this event in its calendar provider.');
        if ($row->conversation_id) { $this->ticket($row->conversation_id); }
        DB::table('qol_calendar_events')->where('id', $row->id)->delete();
        return response()->json(['ok' => true]);
    }
}
