<?php

namespace FreescoutQOL\Controllers;

use FreescoutQOL\Calendar\CalendarProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CalendarController
{
    public function index(Request $request)
    {
        $ticket = $request->filled('ticket') ? $this->ticket($request->input('ticket')) : null;
        $connections = DB::table('qol_calendar_connections')->where('user_id', Auth::id())->pluck('provider')->all();
        $configured = [];
        foreach (['google', 'microsoft'] as $provider) {
            $settings = CalendarProvider::settings($provider);
            $configured[$provider] = !empty($settings['client_id']) && !empty($settings['client_secret']);
        }
        return view('freescout-qol::calendar.index', compact('ticket', 'connections', 'configured'));
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
        return view('freescout-qol::calendar.settings', compact('clients'));
    }

    public function connect(Request $request, $provider)
    {
        $settings = CalendarProvider::settings($provider);
        abort_if(empty($settings['client_id']) || empty($settings['client_secret']), 422, 'Ask an administrator to configure this provider first.');
        $state = bin2hex(random_bytes(32));
        $verifier = bin2hex(random_bytes(32));
        $request->session()->put('qol_calendar_oauth', ['state' => $state, 'verifier' => $verifier, 'provider' => $provider, 'user_id' => Auth::id(), 'expires' => time() + 600]);
        $params = ['client_id' => $settings['client_id'], 'redirect_uri' => route('qol.calendar.callback', ['provider' => $provider]),
            'response_type' => 'code', 'scope' => CalendarProvider::endpoints($provider)['scope'], 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'];
        if ($provider === 'google') { $params['access_type'] = 'offline'; $params['prompt'] = 'consent'; }
        return redirect()->away(CalendarProvider::endpoints($provider)['authorize'].'?'.http_build_query($params));
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
            return $this->remote(function () use ($request, $description, $start, $end) {
                (new CalendarProvider())->create($request->input('source'), Auth::id(), $request->input('calendar'), [
                    'title' => $request->input('title'), 'description' => $description, 'start' => $start->format(DATE_RFC3339),
                    'end' => $end->format(DATE_RFC3339), 'timezone' => $request->input('timezone')]);
                return ['ok' => true];
            });
        }
        $data = ['user_id' => Auth::id(), 'conversation_id' => $ticket ? $ticket->id : null, 'title' => $request->input('title'),
            'description' => $request->input('description'), 'timezone' => $request->input('timezone'),
            'starts_at' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'ends_at' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($request->filled('id')) {
            $row = DB::table('qol_calendar_events')->where('user_id', Auth::id())->where('id', $request->input('id'))->first();
            abort_unless($row, 404);
            if ($row->conversation_id) { $this->ticket($row->conversation_id); }
            DB::table('qol_calendar_events')->where('id', $row->id)->update($data);
        } else {
            $data['created_at'] = $data['updated_at'];
            DB::table('qol_calendar_events')->insert($data);
        }
        return response()->json(['ok' => true]);
    }

    public function delete(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $row = DB::table('qol_calendar_events')->where('user_id', Auth::id())->where('id', $request->input('id'))->first();
        abort_unless($row, 404);
        if ($row->conversation_id) { $this->ticket($row->conversation_id); }
        DB::table('qol_calendar_events')->where('id', $row->id)->delete();
        return response()->json(['ok' => true]);
    }
}
