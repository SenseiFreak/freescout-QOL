@extends('layouts.app')

@section('title', __('QoL'))

@section('content')
            <p><a href="{{ route('qol.calendar.settings') }}">{{ __('Calendar setup and connections') }}</a></p>
    <div class="container form-container">
        <div class="row"><div class="col-xs-12">
            <h2>{{ __('QoL') }}</h2>
            <p class="text-help">{{ __('Quality-of-life improvements currently active in FreeScout.') }}</p>
            <ul>
                <li>{{ __('Conversation sorting is remembered for each user.') }}</li>
                <li>{{ __('The New menu provides Ticket and Contact shortcuts.') }}</li>
                <li>{{ __('Selected conversations can be merged from the bulk action bar.') }}</li>
                <li>{{ __('Incoming email dates are used for Waiting Since.') }}</li>
            </ul>
        </div></div>
    </div>
@endsection
