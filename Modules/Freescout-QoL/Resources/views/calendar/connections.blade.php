<div id="qol-calendar-connections" data-token="{{ csrf_token() }}">
            <h4>{{ __('Connections') }}</h4>
            @foreach (['google' => 'Google Calendar', 'microsoft' => 'Microsoft 365'] as $provider => $label)
                <form class="qc-connection-form" method="POST" action="{{ in_array($provider, $connections) ? route('qol.calendar.disconnect') : route('qol.calendar.connect', ['provider' => $provider]) }}" data-provider="{{ $label }}">
                    {{ csrf_field() }}<input type="hidden" name="provider" value="{{ $provider }}">
                    <button type="submit" class="btn btn-default qc-connect">{{ in_array($provider, $connections) ? __('Disconnect').' '.$label : __('Connect').' '.$label }}</button>
                </form>
                @if (!$configured[$provider] && !in_array($provider, $connections))
                    <p id="qc-setup-{{ $provider }}" class="qc-hint">
                        {{ __('Client ID and client secret have not both been saved for :provider.', ['provider' => $label]) }}
                        @if (Auth::user()->isAdmin())
                            <a href="{{ route('qol.calendar.settings') }}">{{ __('Open Calendar setup to save them.') }}</a>
                        @else
                            {{ __('Ask an administrator to complete Calendar setup.') }}
                        @endif
                    </p>
                @endif
            @endforeach
            <p id="qc-connection-status" role="status" aria-live="polite"></p>
            @if (Auth::user()->isAdmin())<p><a href="{{ route('qol.calendar.settings') }}">{{ __('Calendar setup') }}</a></p>@endif
            <p class="qc-hint">{{ __('Google is recommended. Microsoft 365 supports Exchange Online. IMAP and on-premises Exchange calendar sync are unavailable.') }}</p>
</div>
<script src="{{ asset('modules/qol/js/calendar-connections.js') }}?v=1.3.5"></script>
