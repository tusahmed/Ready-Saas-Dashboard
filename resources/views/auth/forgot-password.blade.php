@extends('layouts.guest')
@section('title', __('app.forgot_password'))
@section('content')
<div class="auth-heading"><h1 class="page-title">{{ __('app.reset_password') }}</h1><p class="page-description">{{ __('app.reset_description') }}</p></div>
<form action="{{ route('password.email') }}" method="POST" class="auth-form">
    @csrf
    <div class="form-group"><label class="form-label" for="email">{{ __('app.email') }}</label><input class="form-input" id="email" name="email" type="email" dir="ltr" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@company.com"></div>
    <button class="btn btn-primary auth-submit" type="submit">{{ __('app.send_reset_link') }}<x-icon name="mail" /></button>
    <a class="btn btn-ghost auth-submit" href="{{ route('login') }}">{{ __('app.back_to_login') }}</a>
</form>
@endsection
