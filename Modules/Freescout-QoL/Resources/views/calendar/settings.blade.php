@extends('layouts.app')
@section('title', __('Calendar setup'))
@section('content')
<div class="container form-container">
    <h2>{{ __('Calendar setup') }}</h2>
    @include('partials/flash_messages')
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p><a href="{{ route('qol.calendar') }}">&larr; {{ __('Calendar') }}</a></p>
    <p>{{ __('Register an OAuth web application with each provider you want to enable. Credentials are encrypted; each agent connects their own account.') }}</p>
    @foreach (['google' => 'Google Calendar', 'microsoft' => 'Microsoft 365 / Exchange Online'] as $provider => $label)
    <section class="panel panel-default"><div class="panel-body">
        <h3>{{ $label }}</h3>
        <p>{{ $provider === 'google' ? __('Enable the Google Calendar API and configure the OAuth consent screen. Add test users while the app is in testing.') : __('Register an application in Microsoft Entra with delegated Calendars.ReadWrite and offline_access permissions. Use the Web redirect platform.') }}</p>
        <p>{{ __('Authorized redirect URI (copy exactly):') }} <code>{{ route('qol.calendar.callback', ['provider' => $provider]) }}</code></p>
        <form method="POST" action="{{ route('qol.calendar.settings.save') }}">
            {{ csrf_field() }}
            <input type="hidden" name="provider" value="{{ $provider }}">
            <div class="form-group"><label for="{{ $provider }}-id">{{ __('Client ID') }}</label><input id="{{ $provider }}-id" class="form-control" name="client_id" value="{{ $clients[$provider] }}" required autocomplete="off"></div>
            <div class="form-group"><label for="{{ $provider }}-secret">{{ __('Client secret (leave blank to retain)') }}</label><input id="{{ $provider }}-secret" class="form-control" type="password" name="client_secret" autocomplete="new-password"></div>
            <button class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div></section>
    @endforeach
    <p>{{ __('IMAP does not synchronize calendars. CalDAV and on-premises Exchange are not supported by this version.') }}</p>
</div>
@endsection
