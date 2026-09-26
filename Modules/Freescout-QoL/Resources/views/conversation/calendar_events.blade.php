<section id="qol-ticket-events" class="conv-sidebar-block" data-url="{{ route('qol.calendar.ticket-events', ['ticket' => $ticket->id]) }}" aria-labelledby="qol-ticket-events-heading">
    <h3 id="qol-ticket-events-heading"><i class="glyphicon glyphicon-calendar"></i> {{ __('Calendar events') }}</h3>
    <div class="qol-ticket-events-list">
        @foreach ($events as $event)
            <article class="qol-ticket-event">
                <strong>{{ $event['title'] }}</strong>
                <div>{{ $event['start'] }} &ndash; {{ $event['end'] }}</div>
                <small>{{ $event['timezone'] }}</small>
                <div class="text-help">{{ $event['imported'] ? __('Provider creator') : __('Created by') }}: {{ $event['creator'] }}</div>
                <small class="text-help">{{ __('Created') }}: {{ $event['created_at'] }} UTC</small>
                @if ($event['url'])<div><a href="{{ $event['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('Open calendar event') }}</a></div>@endif
                @if ($event['source'] !== 'local')<small class="text-help">{{ __('Recorded event times. Open the provider for subsequent changes.') }}</small>@endif
            </article>
        @endforeach
        @if (!count($events))<p class="text-help">{{ $ready ? __('No linked calendar events yet.') : __('Run php artisan migrate to enable calendar history.') }}</p>@endif
    </div>
    <button type="button" class="btn btn-default btn-xs qol-ticket-events-refresh">{{ __('Refresh') }}</button>
    @if ($ready)
        <button type="button" class="btn btn-link btn-xs qol-ticket-events-import" data-url="{{ route('qol.calendar.ticket-events.import') }}" data-ticket="{{ $ticket->id }}" data-token="{{ csrf_token() }}">{{ __('Find earlier Google events') }}</button>
        <p class="text-help"><small>{{ __('Searches your default Google calendar for events containing this ticket link.') }}</small></p>
    @endif
    <p class="qol-ticket-events-status text-help" role="status" aria-live="polite"></p>
</section>
