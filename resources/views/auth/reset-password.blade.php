@extends('layouts.guest')
@section('title', __('app.reset_password'))
@section('content')
<div class="auth-heading"><h1 class="page-title">{{ __('app.set_new_password') }}</h1><p class="page-description">{{ __('app.password_requirements') }}</p></div>
<form action="{{ route('password.update') }}" method="POST" class="auth-form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="form-group"><label class="form-label" for="email">{{ __('app.email') }}</label><input class="form-input" id="email" name="email" type="email" dir="ltr" value="{{ old('email', $email) }}" required autocomplete="username"></div>
    <div class="form-group"><label class="form-label" for="password">{{ __('app.new_password') }}</label><x-password-input id="password" name="password" minlength="12" required autocomplete="new-password" /></div>
    <div class="form-group"><label class="form-label" for="password_confirmation">{{ __('app.confirm_password') }}</label><x-password-input id="password_confirmation" name="password_confirmation" minlength="12" required autocomplete="new-password" /></div>
    <button class="btn btn-primary auth-submit" type="submit">{{ __('app.reset_password') }}<x-icon name="lock" /></button>
</form>
@endsection
