@extends('layouts.app')
@section('title', __('Calendar'))
@section('content')
<link rel="stylesheet" href="{{ asset('modules/qol/css/calendar.css') }}?v=1.3.5">
<div id="qol-calendar" data-default-source="{{ $defaultCalendar['source'] }}" data-default-calendar="{{ $defaultCalendar['calendar'] ?? '' }}" data-events="{{ route('qol.calendar.events') }}" data-calendars="{{ route('qol.calendar.calendars') }}" data-save="{{ route('qol.calendar.save') }}" data-delete="{{ route('qol.calendar.delete') }}" data-token="{{ csrf_token() }}" data-ticket="{{ $ticket ? $ticket->id : '' }}" data-title="{{ $ticket ? '#'.$ticket->number.' '.$ticket->subject : '' }}">
    @include('partials/flash_messages')
    <div class="qc-shell">
        <main class="qc-main">
            <div class="qc-toolbar">
                <div class="qc-navigation">
                    <button type="button" class="btn btn-default" id="qc-today">{{ __('Today') }}</button>
                    <button type="button" class="btn btn-default" id="qc-prev" aria-label="Previous period">&lsaquo;</button>
                    <button type="button" class="btn btn-default" id="qc-next" aria-label="Next period">&rsaquo;</button>
                    <h3 id="qc-heading"></h3>
                </div>
                <div class="qc-selectors">
                    <label for="qc-source" class="sr-only">{{ __('Account') }}</label>
                    <select id="qc-source" class="form-control" title="{{ __('Account') }}"><option value="local">{{ __('FreeScout • Personal') }}</option>
                        @foreach ($connections as $provider)<option value="{{ $provider }}">{{ $provider === 'google' ? 'Google Calendar' : 'Microsoft 365' }}</option>@endforeach
                    </select>
                    <label for="qc-calendar" class="sr-only">{{ __('Calendar') }}</label>
                    <select id="qc-calendar" class="form-control" title="{{ __('Calendar') }}" disabled><option value="">{{ __('Personal calendar') }}</option></select>
                </div>
                <div class="qc-view-actions">
                    <button type="button" class="btn btn-primary qc-new">+ {{ __('New event') }}</button>
                    <label for="qc-view" class="sr-only">{{ __('View') }}</label><select id="qc-view" class="form-control"><option value="month">{{ __('Month') }}</option><option value="week">{{ __('Week') }}</option><option value="day">{{ __('Day') }}</option></select>
                    <button type="button" class="btn btn-default" id="qc-refresh">{{ __('Refresh') }}</button>
                </div>
            </div>
            <p id="qc-status" role="status" aria-live="polite"></p>
            <div id="qc-grid"></div>
            <p class="qc-settings-link"><a href="{{ route('qol.settings') }}#qol-calendar-heading">{{ __('Calendar settings') }}</a></p>
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
<script src="{{ asset('modules/qol/js/calendar.js') }}?v=1.3.4"></script>
@endsection
