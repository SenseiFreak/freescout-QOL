<dialog id="qol-calendar-panel" aria-labelledby="qcp-heading" data-context="{{ route('qol.calendar.ticket-event') }}" data-save="{{ route('qol.calendar.save') }}" data-token="{{ csrf_token() }}">
    <header class="qcp-header"><div><h2 id="qcp-heading">{{ __('Add to calendar') }}</h2><p>{{ __('Schedule an event for this ticket') }}</p></div><button type="button" id="qcp-close" aria-label="{{ __('Close') }}">&times;</button></header>
    <p id="qcp-status" role="status" aria-live="polite"></p>
    <form id="qcp-form">
        <fieldset id="qcp-fields" disabled>
            <input type="hidden" name="ticket">
            <label for="qcp-title">{{ __('Event title') }}</label><input class="form-control" id="qcp-title" name="title" required maxlength="255">
            <label for="qcp-calendar">{{ __('Calendar') }}</label><select class="form-control" id="qcp-calendar" name="destination" required></select>
            <p id="qcp-sharing" class="help-block"></p>
            <label for="qcp-start">{{ __('Start date and time') }}</label><input class="form-control" id="qcp-start" name="start" type="datetime-local" required>
            <label for="qcp-end">{{ __('End date and time') }}</label><input class="form-control" id="qcp-end" name="end" type="datetime-local" required>
            <label for="qcp-timezone">{{ __('Timezone') }}</label><input class="form-control" id="qcp-timezone" name="timezone" required placeholder="Africa/Johannesburg">
            <label for="qcp-description">{{ __('Event details') }}</label><textarea class="form-control" id="qcp-description" name="description" rows="6" maxlength="10000" placeholder="{{ __('Agenda, location, meeting link, or other notes') }}"></textarea>
            <p class="help-block">{{ __('This ticket is linked automatically. Saving keeps you on the ticket.') }}</p>
        </fieldset>
        <footer class="qcp-footer"><button type="button" id="qcp-cancel" class="btn btn-default">{{ __('Cancel') }}</button><button type="submit" id="qcp-save" class="btn btn-primary" disabled>{{ __('Create event') }}</button></footer>
    </form>
</dialog>
