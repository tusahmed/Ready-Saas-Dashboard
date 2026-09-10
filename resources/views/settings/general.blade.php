@extends('layouts.app')
@section('title', __('app.general_settings'))
@section('content')
@php
    $businessDisplay = $settings->business_name ?: (auth()->user()->tenant?->name ?? 'Orbit');
    $businessInitials = collect(preg_split('/\s+/u', trim($businessDisplay)))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $logoUrl = $settings->logo_url;
@endphp
<div class="page-heading"><div><h1 class="page-title">{{ __('app.general_settings') }}</h1><p class="page-description">{{ __('app.general_settings_description') }}</p></div></div>
@include('settings.tabs')
<form class="split-grid form-layout settings-form" action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PATCH')
    <div class="form-main">
        <section class="card">
            <div class="card-header"><div><h2 class="card-title">{{ __('app.business_identity') }}</h2><p class="card-subtitle">{{ __('app.business_identity_description') }}</p></div><span class="section-icon"><x-icon name="building" /></span></div>
            <div class="card-body form-grid">
                <div class="form-group"><label class="form-label" for="business_name">{{ __('app.business_name') }} <span class="required">*</span></label><input class="form-input" id="business_name" name="business_name" value="{{ old('business_name', $settings->business_name) }}" required maxlength="150" autocomplete="organization" @error('business_name') aria-invalid="true" aria-describedby="business-name-error" @enderror>@error('business_name')<p class="form-error" id="business-name-error">{{ $message }}</p>@enderror</div>
                <div class="form-group"><label class="form-label" for="business_email">{{ __('app.business_email') }}</label><input class="form-input" id="business_email" name="business_email" type="email" dir="ltr" value="{{ old('business_email', $settings->business_email) }}" maxlength="255" autocomplete="email" @error('business_email') aria-invalid="true" aria-describedby="business-email-error" @enderror>@error('business_email')<p class="form-error" id="business-email-error">{{ $message }}</p>@enderror</div>
                <div class="form-group full-width"><label class="form-label" for="description">{{ __('app.description') }}</label><textarea class="form-textarea" id="description" name="description" rows="3" maxlength="2000">{{ old('description', $settings->description) }}</textarea></div>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><div><h2 class="card-title">{{ __('app.business_contact_details') }}</h2><p class="card-subtitle">{{ __('app.business_contact_details_description') }}</p></div><x-icon name="globe" /></div>
            <div class="card-body form-grid">
                <div class="form-group"><label class="form-label" for="phone">{{ __('app.phone') }}</label><input class="form-input" id="phone" name="phone" type="tel" dir="ltr" value="{{ old('phone', $settings->phone) }}" maxlength="40" autocomplete="tel"></div>
                <div class="form-group"><label class="form-label" for="website">{{ __('app.website') }}</label><input class="form-input" id="website" name="website" type="url" dir="ltr" value="{{ old('website', $settings->website) }}" maxlength="255" placeholder="https://example.com" autocomplete="url"></div>
                <div class="form-group full-width"><label class="form-label" for="country">{{ __('app.country') }}</label><input class="form-input" id="country" name="country" value="{{ old('country', $settings->country) }}" maxlength="120" autocomplete="country-name"></div>
                <div class="form-group full-width"><label class="form-label" for="address">{{ __('app.address') }}</label><textarea class="form-textarea" id="address" name="address" rows="3" maxlength="1000" autocomplete="street-address">{{ old('address', $settings->address) }}</textarea></div>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><div><h2 class="card-title">{{ __('app.business_registration') }}</h2><p class="card-subtitle">{{ __('app.business_registration_description') }}</p></div><x-icon name="briefcase" /></div>
            <div class="card-body form-grid">
                <div class="form-group"><label class="form-label" for="tax_number">{{ __('app.tax_number') }}</label><input class="form-input" id="tax_number" name="tax_number" value="{{ old('tax_number', $settings->tax_number) }}" maxlength="120" autocomplete="off"></div>
                <div class="form-group"><label class="form-label" for="registration_number">{{ __('app.registration_number') }}</label><input class="form-input" id="registration_number" name="registration_number" value="{{ old('registration_number', $settings->registration_number) }}" maxlength="120" autocomplete="off"></div>
            </div>
        </section>
        <div class="form-footer settings-submit"><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ __('app.save_settings') }}</button></div>
    </div>
    <aside class="form-aside settings-aside">
        <section class="card settings-logo-card" data-logo-editor>
            <div class="card-header"><div><h2 class="card-title">{{ __('app.workspace_logo') }}</h2><p class="card-subtitle">{{ __('app.workspace_logo_description') }}</p></div></div>
            <div class="card-body stack">
                <figure class="settings-logo-preview" aria-label="{{ __('app.logo_preview') }}">
                    <img data-logo-preview-image @if($logoUrl) src="{{ $logoUrl }}" @else hidden @endif alt="{{ __('app.logo_preview') }}">
                    <span class="settings-logo-fallback" data-logo-preview-fallback @if($logoUrl) hidden @endif aria-hidden="true">{{ $businessInitials }}</span>
                </figure>
                <div class="form-group"><label class="sr-only" for="logo">{{ __('app.logo_upload') }}</label><div class="settings-upload-control"><span class="btn btn-secondary" aria-hidden="true"><x-icon name="plus" />{{ __('app.logo_upload') }}</span><input class="settings-file-input" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help logo-status" data-logo-input data-invalid-message="{{ __('app.logo_preview_invalid') }}" data-selected-message="{{ __('app.logo_preview_selected') }}" @error('logo') aria-invalid="true" @enderror></div><p class="form-help" id="logo-help">{{ __('app.logo_upload_help') }}</p><p class="form-help settings-logo-status" id="logo-status" data-logo-status aria-live="polite">{{ __('app.logo_saved_hint') }}</p>@error('logo')<p class="form-error">{{ $message }}</p>@enderror</div>
                @if($settings->logo_path)<label class="checkbox-row"><input type="checkbox" name="remove_logo" value="1" data-logo-remove @checked(old('remove_logo'))><span>{{ __('app.remove_logo') }}</span></label>@endif
            </div>
        </section>
        <div class="notice"><x-icon name="shield" /><p>{{ auth()->user()->isPlatform() ? __('app.workspace_settings_scope_platform') : __('app.workspace_settings_scope_client') }}</p></div>
    </aside>
</form>
@endsection
