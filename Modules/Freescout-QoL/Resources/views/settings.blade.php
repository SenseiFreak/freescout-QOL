@extends('layouts.app')

@section('title', __('QoL'))

@section('content')
    <div class="container form-container">
        <div class="row"><div class="col-xs-12">
            <h2>{{ __('QoL') }}</h2>
            @include('partials/flash_messages')
            @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            @if (Auth::user()->isAdmin())
            <section aria-labelledby="qol-general-heading">
            <h3 id="qol-general-heading">{{ __('General') }}</h3>
            <p class="text-help">{{ __('These settings apply to all FreeScout users.') }}</p>
            <form class="form-horizontal margin-top" method="POST" action="{{ route('qol.settings.save') }}">
                {{ csrf_field() }}
                <div class="form-group">
                    <div class="col-sm-8 col-sm-offset-2">
                        <div class="checkbox">
                            <label><input type="checkbox" name="stay_on_ticket" value="1" @if ($settings['stay_on_ticket']) checked @endif> {{ __('Stay on the current ticket after replying or adding a note') }}</label>
                            <p class="help-block">{{ __('When a ticket is set to Closed, FreeScout moves to the next active ticket.')}}</p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-8 col-sm-offset-2">
                        <div class="checkbox">
                            <label><input type="checkbox" name="gate_subject_editing" value="1" @if ($settings['gate_subject_editing']) checked @endif> {{ __('Require Edit Subject before changing a ticket subject') }}</label>
                            <p class="help-block">{{ __('Adds an Edit Subject button beside the ticket actions and prevents accidental edits from a title click.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-8 col-sm-offset-2">
                        <div class="checkbox">
                            <label><input type="checkbox" name="drag_drop_attachments" value="1" @if ($settings['drag_drop_attachments']) checked @endif> {{ __('Allow drag-and-drop attachments in replies and new tickets') }}</label>
                            <p class="help-block">{{ __('Dropped images are embedded; other files are added as attachments.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="form-group"><div class="col-sm-8 col-sm-offset-2"><button class="btn btn-primary">{{ __('Save') }}</button></div></div>
            </form>
            </section>
            <hr>
            @endif
            <section aria-labelledby="qol-calendar-heading">
                <h3 id="qol-calendar-heading">{{ __('Calendar') }}</h3>
                <p class="text-help">{{ __('Choose the calendar that opens by default for your account. Other users keep their own default.') }}</p>
                @foreach ($calendarPreferences['errors'] as $calendarError)<p class="alert alert-warning">{{ $calendarError }}</p>@endforeach
                <form class="form-horizontal margin-top" method="POST" action="{{ route('qol.calendar.default.save') }}">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="qol-default-calendar">{{ __('Default calendar') }}</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="qol-default-calendar" name="default_calendar">
                                @foreach ($calendarPreferences['options'] as $calendarOption)
                                    <option value="{{ json_encode(['source' => $calendarOption['source'], 'calendar' => $calendarOption['calendar']]) }}" @if ($calendarOption['source'] === $calendarPreferences['saved']['source'] && $calendarOption['calendar'] === ($calendarPreferences['saved']['calendar'] ?? '')) selected @endif>{{ $calendarOption['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">{{ __('Connect Google or Microsoft below to make those calendars available here. This default also applies when scheduling a ticket.') }}</p>
                        </div>
                    </div>
                    <div class="form-group"><div class="col-sm-8 col-sm-offset-2"><button type="submit" class="btn btn-primary">{{ __('Save default calendar') }}</button></div></div>
                </form>
                @include('qol::calendar.connections')
                <p><a href="{{ route('qol.calendar') }}">{{ __('Open Calendar') }}</a></p>
            </section>
        </div></div>
    </div>
@endsection
