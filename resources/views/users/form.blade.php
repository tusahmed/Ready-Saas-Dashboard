@extends('layouts.app')
@section('title', $user->exists ? __('app.edit_user_title') : __('app.add_user'))
@section('content')
@php($protectedUser = $user->exists && ($user->is_super_admin || $user->role?->is_owner || $user->id === auth()->id()))
<div class="page-heading"><div><a class="back-link" href="{{ route('users.index') }}"><x-icon name="arrow-left" />{{ __('app.back_to_users') }}</a><h1 class="page-title">{{ $user->exists ? __('app.edit_user_title') : __('app.add_user') }}</h1><p class="page-description">{{ __('app.user_form_description') }}</p></div></div>
@include('partials.management-tabs')
<form action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" method="POST" class="split-grid form-layout">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="form-main">
        <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.personal_information') }}</h2><p class="card-subtitle">{{ __('app.personal_information_description') }}</p></div><x-icon name="user" /></div><div class="card-body form-grid">
            <div class="form-group"><label class="form-label" for="name">{{ __('app.full_name') }} <span class="required">*</span></label><input class="form-input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"></div>
            <div class="form-group"><label class="form-label" for="email">{{ __('app.email') }} <span class="required">*</span></label><input class="form-input" id="email" name="email" type="email" dir="ltr" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email"></div>
            <div class="form-group"><label class="form-label" for="phone">{{ __('app.phone') }}</label><input class="form-input" id="phone" name="phone" type="tel" dir="ltr" value="{{ old('phone', $user->phone) }}" maxlength="40" autocomplete="tel"></div>
            <div class="form-group"><label class="form-label" for="job_title">{{ __('app.job_title') }}</label><input class="form-input" id="job_title" name="job_title" value="{{ old('job_title', $user->job_title) }}" maxlength="120" autocomplete="organization-title"></div>
            <div class="form-group full-width"><label class="form-label" for="bio">{{ __('app.bio') }}</label><textarea class="form-textarea" id="bio" name="bio" rows="4" maxlength="2000">{{ old('bio', $user->bio) }}</textarea><p class="form-help">{{ __('app.bio_hint') }}</p></div>
        </div></section>
        <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.account_security') }}</h2><p class="card-subtitle">{{ $user->exists ? __('app.password_optional') : __('app.password_requirements') }}</p></div><x-icon name="lock" /></div><div class="card-body form-grid">
            <div class="form-group"><label class="form-label" for="password">{{ $user->exists ? __('app.new_password') : __('app.password') }} @unless($user->exists)<span class="required">*</span>@endunless</label><x-password-input id="password" name="password" minlength="12" autocomplete="new-password" :required="!$user->exists" /></div>
            <div class="form-group"><label class="form-label" for="password_confirmation">{{ __('app.confirm_password') }}</label><x-password-input id="password_confirmation" name="password_confirmation" minlength="12" autocomplete="new-password" :required="!$user->exists" /></div>
        </div></section>
        <div class="form-footer"><a class="btn btn-secondary" href="{{ route('users.index') }}">{{ __('app.cancel') }}</a><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ $user->exists ? __('app.save_changes') : __('app.create_user') }}</button></div>
    </div>
    <aside class="form-aside"><section class="card"><div class="card-header"><h2 class="card-title">{{ __('app.role_and_access') }}</h2><x-icon name="shield" /></div><div class="card-body stack">
        <div class="form-group"><label class="form-label" for="role_id">{{ __('app.role') }}</label><select class="form-select" id="role_id" name="role_id" @disabled($protectedUser)><option value="">{{ __('app.no_role') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)old('role_id', $user->role_id) === (string)$role->id)>{{ $role->name }}</option>@endforeach</select><p class="form-help">{{ __('app.role_selection_hint') }}</p></div>
        <div class="form-group"><label class="form-label" for="status">{{ __('app.status') }}</label><select class="form-select" id="status" name="status" @disabled($protectedUser)><option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>{{ __('app.active') }}</option><option value="inactive" @selected(old('status', $user->status) === 'inactive')>{{ __('app.inactive') }}</option></select><p class="form-help">{{ __('app.inactive_user_hint') }}</p></div>
        @if($protectedUser)<input type="hidden" name="role_id" value="{{ $user->role_id }}"><input type="hidden" name="status" value="{{ $user->status }}"><div class="notice"><x-icon name="shield-check" /><p>{{ __('app.protected_user_hint') }}</p></div>@endif
        @if($user->exists)<dl class="detail-list"><div><dt>{{ __('app.joined') }}</dt><dd>{{ $user->created_at->translatedFormat('d M Y') }}</dd></div><div><dt>{{ __('app.last_login') }}</dt><dd>{{ $user->last_login_at?->diffForHumans() ?? __('app.never_logged_in') }}</dd></div></dl>@endif
    </div></section></aside>
</form>
@endsection
