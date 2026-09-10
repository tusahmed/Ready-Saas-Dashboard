@extends('layouts.app')
@section('title', $client->exists ? __('app.edit_client') : __('app.add_client'))
@section('content')
<div class="page-heading"><div><a class="back-link" href="{{ route('admin.clients.index') }}"><x-icon name="arrow-left" />{{ __('app.back_to_clients') }}</a><h1 class="page-title">{{ $client->exists ? __('app.edit_client') : __('app.add_client') }}</h1><p class="page-description">{{ __('app.client_form_description') }}</p></div></div>
<form action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}" method="POST" class="split-grid form-layout">
    @csrf @if($client->exists) @method('PUT') @endif
    <div class="form-main">
        <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.company_information') }}</h2><p class="card-subtitle">{{ __('app.company_information_description') }}</p></div><x-icon name="building-2" /></div><div class="card-body form-grid">
            <div class="form-group"><label class="form-label" for="name">{{ __('app.company_name') }} <span class="required">*</span></label><input class="form-input" id="name" name="name" value="{{ old('name', $client->name) }}" required maxlength="120" autocomplete="organization"></div>
            <div class="form-group"><label class="form-label" for="slug">{{ __('app.workspace_slug') }} <span class="required">*</span></label><input class="form-input" id="slug" name="slug" dir="ltr" value="{{ old('slug', $client->slug) }}" required maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="acme-studio"><p class="form-help">{{ __('app.slug_hint') }}</p></div>
            <div class="form-group"><label class="form-label" for="email">{{ __('app.company_email') }} <span class="required">*</span></label><input class="form-input" id="email" name="email" type="email" dir="ltr" value="{{ old('email', $client->email) }}" required maxlength="255" autocomplete="email"></div>
            <div class="form-group"><label class="form-label" for="phone">{{ __('app.phone') }}</label><input class="form-input" id="phone" name="phone" type="tel" dir="ltr" value="{{ old('phone', $client->phone) }}" maxlength="40" autocomplete="tel"></div>
            <div class="form-group full-width"><label class="form-label" for="website">{{ __('app.website') }}</label><input class="form-input" id="website" name="website" type="url" dir="ltr" value="{{ old('website', $client->website) }}" maxlength="255" placeholder="https://example.com" autocomplete="url"></div>
            <div class="form-group full-width"><label class="form-label" for="description">{{ __('app.description') }}</label><textarea class="form-textarea" id="description" name="description" rows="4" maxlength="2000">{{ old('description', $client->description) }}</textarea></div>
        </div></section>
        @unless($client->exists)
        <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.workspace_owner') }}</h2><p class="card-subtitle">{{ __('app.workspace_owner_description') }}</p></div><x-icon name="user-check" /></div><div class="card-body form-grid">
            <div class="form-group"><label class="form-label" for="owner_name">{{ __('app.owner_name') }} <span class="required">*</span></label><input class="form-input" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required maxlength="120" autocomplete="off"></div>
            <div class="form-group"><label class="form-label" for="owner_email">{{ __('app.owner_email') }} <span class="required">*</span></label><input class="form-input" id="owner_email" name="owner_email" type="email" dir="ltr" value="{{ old('owner_email') }}" required maxlength="255" autocomplete="off"></div>
            <div class="form-group"><label class="form-label" for="owner_password">{{ __('app.password') }} <span class="required">*</span></label><x-password-input id="owner_password" name="owner_password" minlength="12" required autocomplete="new-password" /><p class="form-help">{{ __('app.password_requirements') }}</p></div>
            <div class="form-group"><label class="form-label" for="owner_password_confirmation">{{ __('app.confirm_password') }} <span class="required">*</span></label><x-password-input id="owner_password_confirmation" name="owner_password_confirmation" minlength="12" required autocomplete="new-password" /></div>
        </div></section>
        @endunless
        <div class="form-footer"><a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">{{ __('app.cancel') }}</a><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ $client->exists ? __('app.save_changes') : __('app.create_client') }}</button></div>
    </div>
    <aside class="form-aside"><section class="card"><div class="card-header"><h2 class="card-title">{{ __('app.workspace_settings') }}</h2><x-icon name="settings" /></div><div class="card-body stack">
        <div class="form-group"><label class="form-label" for="plan">{{ __('app.plan') }}</label><select class="form-select" id="plan" name="plan">@foreach(['starter','growth','enterprise'] as $plan)<option value="{{ $plan }}" @selected(old('plan', $client->plan ?? 'starter') === $plan)>{{ __('app.plan_'.$plan) }}</option>@endforeach</select><p class="form-help">{{ __('app.plan_hint') }}</p></div>
        <div class="form-group"><label class="form-label" for="status">{{ __('app.status') }}</label><select class="form-select" id="status" name="status"><option value="active" @selected(old('status', $client->status ?? 'active') === 'active')>{{ __('app.active') }}</option><option value="suspended" @selected(old('status', $client->status) === 'suspended')>{{ __('app.suspended') }}</option></select><p class="form-help">{{ __('app.suspended_hint') }}</p></div>
        <div class="form-group"><label class="form-label" for="trial_ends_at">{{ __('app.trial_ends_at') }}</label><input class="form-input" id="trial_ends_at" name="trial_ends_at" type="date" value="{{ old('trial_ends_at', $client->trial_ends_at?->format('Y-m-d')) }}"><p class="form-help">{{ __('app.optional') }}</p></div>
        <div class="notice"><x-icon name="shield-check" /><p>{{ __('app.tenant_isolation_hint') }}</p></div>
    </div></section></aside>
</form>
@endsection
