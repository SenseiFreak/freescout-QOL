@php
    $qolMailboxes = Auth::user() ? Auth::user()->mailboxesCanView(true) : collect();
@endphp
<li class="dropdown qol-new-dropdown">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false" aria-haspopup="true" id="qol-new-button">
        {{ __('New') }} <span class="caret"></span>
    </a>
    <ul class="dropdown-menu dropdown-with-icons" id="qol-new-menu">
        @if ($qolMailboxes->count() == 1)
            <li><a href="{{ route('conversations.create', ['mailbox_id' => $qolMailboxes->first()->id]) }}"><i class="glyphicon glyphicon-envelope"></i> {{ __('Ticket') }}</a></li>
        @elseif ($qolMailboxes->count() > 1)
            <li class="dropdown-submenu">
                <a href="#"><i class="glyphicon glyphicon-envelope"></i> {{ __('Ticket') }}</a>
                <ul class="dropdown-menu">
                    @foreach ($qolMailboxes as $qolMailbox)
                        <li><a href="{{ route('conversations.create', ['mailbox_id' => $qolMailbox->id]) }}">{{ $qolMailbox->name }}</a></li>
                    @endforeach
                </ul>
            </li>
        @endif
        <li><a href="{{ route('qol.contact.create') }}"><i class="glyphicon glyphicon-user"></i> {{ __('Contact') }}</a></li>
    </ul>
</li>
