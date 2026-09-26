@extends('layouts.app')
@section('title', __('Calendar'))
@section('content')
<link rel="stylesheet" href="{{ asset('vendor/freescout-qol/css/calendar.css') }}">
<div id="qol-calendar" data-events="{{ route('qol.calendar.events') }}" data-calendars="{{ route('qol.calendar.calendars') }}" data-save="{{ route('qol.calendar.save') }}" data-delete="{{ route('qol.calendar.delete') }}" data-token="{{ csrf_token() }}" data-ticket="{{ $ticket ? $ticket->id : '' }}" data-title="{{ $ticket ? '#'.$ticket->number.' '.$ticket->subject : '' }}">
    @include('partials/flash_messages')
    <div class="qc-shell">
        <aside class="qc-sidebar">
            <h2>{{ __('Calendar') }}</h2>
            <button type="button" class="btn btn-primary qc-new">+ {{ __('New event') }}</button>
            <h4>{{ __('My calendars') }}</h4>
            <label for="qc-source">{{ __('Account') }}</label>
            <select id="qc-source" class="form-control"><option value="local">{{ __('FreeScout • Personal') }}</option>
            @foreach ($connections as $provider)<option value="{{ $provider }}">{{ $provider === 'google' ? 'Google Calendar' : 'Microsoft 365' }}</option>@endforeach
            </select>
            <label for="qc-calendar">{{ __('Calendar') }}</label><select id="qc-calendar" class="form-control" disabled><option value="">{{ __('Personal calendar') }}</option></select>
            <p class="qc-hint">{{ __('Your FreeScout calendar is private. Connected calendars refresh when opened and every minute.') }}</p>
            <h4>{{ __('Connections') }}</h4>
            @foreach (['google' => 'Google Calendar', 'microsoft' => 'Microsoft 365'] as $provider => $label)
                <form method="POST" action="{{ in_array($provider, $connections) ? route('qol.calendar.disconnect') : route('qol.calendar.connect', ['provider' => $provider]) }}">
                    {{ csrf_field() }}<input type="hidden" name="provider" value="{{ $provider }}">
                    <button class="btn btn-default qc-connect" @if (!$configured[$provider]) disabled @endif>{{ in_array($provider, $connections) ? __('Disconnect').' '.$label : __('Connect').' '.$label }}</button>
                </form>
            @endforeach
            @if (Auth::user()->isAdmin())<p><a href="{{ route('qol.calendar.settings') }}">{{ __('Calendar setup') }}</a></p>@endif
            <p class="qc-hint">{{ __('Google is recommended. Microsoft 365 supports Exchange Online. IMAP and on-premises Exchange calendar sync are unavailable.') }}</p>
        </aside>
        <main class="qc-main">
            <div class="qc-toolbar">
                <button type="button" class="btn btn-default" id="qc-today">{{ __('Today') }}</button>
                <button type="button" class="btn btn-default" id="qc-prev" aria-label="Previous period">&lsaquo;</button>
                <button type="button" class="btn btn-default" id="qc-next" aria-label="Next period">&rsaquo;</button>
                <h3 id="qc-heading"></h3>
                <label for="qc-view" class="sr-only">{{ __('View') }}</label><select id="qc-view" class="form-control"><option value="month">{{ __('Month') }}</option><option value="week">{{ __('Week') }}</option><option value="day">{{ __('Day') }}</option></select>
                <button type="button" class="btn btn-default" id="qc-refresh">{{ __('Refresh') }}</button>
            </div>
            <p id="qc-status" role="status" aria-live="polite"></p>
            <div id="qc-grid"></div>
        </main>
    </div>
    <dialog id="qc-dialog" aria-labelledby="qc-dialog-title">
        <form id="qc-form">
            <h3 id="qc-dialog-title">{{ __('Calendar event') }}</h3>
            <input type="hidden" name="id">
            <label for="qc-title">{{ __('Title') }}</label><input id="qc-title" class="form-control" name="title" maxlength="255" required>
            <div class="qc-dates"><div><label for="qc-start">{{ __('Start') }}</label><input id="qc-start" class="form-control" type="datetime-local" name="start" required></div><div><label for="qc-end">{{ __('End') }}</label><input id="qc-end" class="form-control" type="datetime-local" name="end" required></div></div>
            <label for="qc-timezone">{{ __('Timezone (IANA name)') }}</label><input id="qc-timezone" class="form-control" name="timezone" required list="qc-timezones"><datalist id="qc-timezones"><option value="UTC"><option value="Africa/Johannesburg"><option value="Europe/London"><option value="America/New_York"></datalist>
            <label for="qc-ticket">{{ __('Ticket ID (optional)') }}</label><input id="qc-ticket" class="form-control" type="number" min="1" name="ticket"><p class="qc-hint">{{ __('Use the ID from the ticket URL, or choose Add to calendar on a ticket.') }}</p>
            <label for="qc-description">{{ __('Notes') }}</label><textarea id="qc-description" class="form-control" name="description" maxlength="10000" rows="3"></textarea>
            <p id="qc-destination" class="qc-hint"></p><p id="qc-error" role="alert"></p>
            <div class="qc-dialog-actions"><a id="qc-ticket-link" target="_blank" rel="noopener">{{ __('Open ticket') }}</a><button type="button" id="qc-remove" class="btn btn-danger">{{ __('Delete') }}</button><button type="button" id="qc-cancel" class="btn btn-default">{{ __('Cancel') }}</button><button id="qc-submit" class="btn btn-primary">{{ __('Save event') }}</button></div>
        </form>
    </dialog>
</div>
<script src="{{ asset('vendor/freescout-qol/js/calendar.js') }}"></script>
@endsection
