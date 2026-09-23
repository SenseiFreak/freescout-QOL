@extends('layouts.app')

@section('title', __('New Contact'))

@section('content')
    @include('partials/flash_messages')
    <div class="container form-container">
        <div class="row">
            <div class="col-xs-12">
                <form class="form-horizontal margin-top" method="POST" action="{{ route('qol.contact.store') }}">
                    {{ csrf_field() }}
                    <div class="form-group{{ $errors->has('first_name') ? ' has-error' : '' }}">
                        <label for="first_name" class="col-sm-2 control-label">{{ __('First Name') }}</label>
                        <div class="col-sm-6"><input id="first_name" class="form-control input-sized-lg" name="first_name" value="{{ old('first_name') }}" maxlength="255">@include('partials/field_error', ['field' => 'first_name'])</div>
                    </div>
                    <div class="form-group{{ $errors->has('last_name') ? ' has-error' : '' }}">
                        <label for="last_name" class="col-sm-2 control-label">{{ __('Last Name') }}</label>
                        <div class="col-sm-6"><input id="last_name" class="form-control input-sized-lg" name="last_name" value="{{ old('last_name') }}" maxlength="255">@include('partials/field_error', ['field' => 'last_name'])</div>
                    </div>
                    <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                        <label for="email" class="col-sm-2 control-label">{{ __('Email') }}</label>
                        <div class="col-sm-6"><input id="email" type="email" class="form-control input-sized-lg" name="email" value="{{ old('email') }}" maxlength="191">@include('partials/field_error', ['field' => 'email'])</div>
                    </div>
                    <div class="form-group"><div class="col-sm-offset-2 col-sm-6"><button class="btn btn-primary">{{ __('Save') }}</button> <a class="btn btn-link" href="{{ url()->previous() }}">{{ __('Cancel') }}</a></div></div>
                </form>
            </div>
        </div>
    </div>
@endsection
