@extends('layouts.guest')
@section('title', __('app.login'))
@section('content')
<div class="auth-heading"><h1 class="page-title">{{ __('app.welcome_back') }}</h1><p class="page-description">{{ __('app.login_description') }}</p></div>
<form action="{{ route('login.store') }}" method="POST" class="auth-form">
    @csrf
    <div class="form-group"><label class="form-label" for="email">{{ __('app.email') }}</label><input class="form-input" id="email" name="email" type="email" dir="ltr" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@company.com"></div>
    <div class="form-group"><div class="form-label-row"><label class="form-label" for="password">{{ __('app.password') }}</label><a href="{{ route('password.request') }}">{{ __('app.forgot_password') }}</a></div><x-password-input id="password" name="password" required autocomplete="current-password" placeholder="••••••••" /></div>
    <label class="checkbox-row"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>{{ __('app.remember_me') }}</span></label>
    <button class="btn btn-primary auth-submit" type="submit">{{ __('app.login') }}<x-icon name="arrow-right" /></button>
    <p class="auth-note"><x-icon name="lock" />{{ __('app.login_invite_only') }}</p>
</form>
@endsection
