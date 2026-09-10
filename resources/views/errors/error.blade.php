@extends('layouts.guest')
@section('title', __('app.error_'.$code.'_title'))
@section('content')
<div class="auth-heading error-page"><span class="error-code">{{ $code }}</span><h1 class="page-title">{{ __('app.error_'.$code.'_title') }}</h1><p class="page-description">{{ __('app.error_'.$code.'_description') }}</p></div>
<a class="btn btn-primary auth-submit" href="{{ auth()->check() ? route('dashboard') : route('login') }}"><x-icon name="arrow-left" />{{ auth()->check() ? __('app.back_to_dashboard') : __('app.back_to_login') }}</a>
@endsection
