@extends('layouts.app')
@section('title', __('app.smtp_settings'))
@section('content')
<div class="page-heading"><div><h1 class="page-title">{{ __('app.smtp_settings') }}</h1><p class="page-description">{{ __('app.smtp_settings_description') }}</p></div></div>
@include('settings.tabs')
<form class="settings-smtp-form" action="{{ route('admin.settings.smtp.update') }}" method="POST" autocomplete="off">
    @csrf @method('PATCH')
    <section class="card">
        <div class="card-body settings-mail-enabled">
            <span class="section-icon"><x-icon name="mail" /></span>
            <div><h2 class="card-title">{{ __('app.smtp_enabled') }}</h2><p class="card-subtitle">{{ __('app.smtp_enabled_description') }}</p></div>
            <input type="hidden" name="enabled" value="0"><label class="checkbox-row settings-enable-control"><input type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $settings->enabled))><span>{{ __('app.smtp_enabled') }}</span></label>
        </div>
    </section>
    <div class="settings-smtp-grid">
        <section class="card">
            <div class="card-header"><div><h2 class="card-title">{{ __('app.smtp_connection') }}</h2><p class="card-subtitle">{{ __('app.smtp_connection_description') }}</p></div><x-icon name="settings" /></div>
            <div class="card-body form-grid">
                <div class="form-group full-width"><label class="form-label" for="host">{{ __('app.smtp_host') }}</label><input class="form-input" id="host" name="host" dir="ltr" value="{{ old('host', $settings->host) }}" maxlength="255" placeholder="smtp.example.com" aria-describedby="smtp-host-help" @error('host') aria-invalid="true" @enderror><p class="form-help" id="smtp-host-help">{{ __('app.smtp_host_hint') }}</p></div>
                <div class="form-group"><label class="form-label" for="port">{{ __('app.smtp_port') }}</label><input class="form-input" id="port" name="port" type="number" inputmode="numeric" min="1" max="65535" dir="ltr" value="{{ old('port', $settings->port ?? 587) }}" @error('port') aria-invalid="true" @enderror></div>
                <div class="form-group"><label class="form-label" for="encryption">{{ __('app.smtp_encryption') }}</label><select class="form-select" id="encryption" name="encryption">@foreach(['tls','ssl','none'] as $encryption)<option value="{{ $encryption }}" @selected(old('encryption', $settings->encryption ?? 'tls') === $encryption)>{{ __('app.smtp_encryption_'.$encryption) }}</option>@endforeach</select></div>
                <div class="form-group full-width"><label class="form-label" for="username">{{ __('app.smtp_username') }}</label><input class="form-input" id="username" name="username" dir="ltr" value="{{ old('username', $settings->username) }}" maxlength="255" autocomplete="off" aria-describedby="smtp-username-help"><p class="form-help" id="smtp-username-help">{{ __('app.smtp_username_hint') }}</p></div>
                <div class="form-group full-width"><div class="form-label-row"><label class="form-label" for="smtp_password">{{ __('app.smtp_password') }}</label><span class="badge {{ $settings->has_password ? 'badge-success' : 'badge-neutral' }}">{{ $settings->has_password ? __('app.smtp_password_saved') : __('app.smtp_password_empty') }}</span></div><x-password-input id="smtp_password" name="smtp_password" autocomplete="new-password" maxlength="2048" aria-describedby="smtp-password-help" /><p class="form-help" id="smtp-password-help">{{ __('app.smtp_password_keep_hint') }}</p></div>
                @if($settings->has_password)<div class="form-group full-width"><label class="checkbox-row"><input type="checkbox" name="clear_password" value="1" @checked(old('clear_password')) aria-describedby="smtp-clear-password-help"><span>{{ __('app.smtp_clear_password') }}</span></label><p class="form-help" id="smtp-clear-password-help">{{ __('app.smtp_clear_password_hint') }}</p></div>@endif
            </div>
        </section>
        <section class="card">
            <div class="card-header"><div><h2 class="card-title">{{ __('app.smtp_sender') }}</h2><p class="card-subtitle">{{ __('app.smtp_sender_description') }}</p></div><x-icon name="send" /></div>
            <div class="card-body stack">
                <div class="form-group"><label class="form-label" for="from_name">{{ __('app.smtp_from_name') }}</label><input class="form-input" id="from_name" name="from_name" value="{{ old('from_name', $settings->from_name) }}" maxlength="120" autocomplete="organization" @error('from_name') aria-invalid="true" @enderror></div>
                <div class="form-group"><label class="form-label" for="from_address">{{ __('app.smtp_from_address') }}</label><input class="form-input" id="from_address" name="from_address" type="email" dir="ltr" value="{{ old('from_address', $settings->from_address) }}" maxlength="255" autocomplete="email" aria-describedby="smtp-from-address-help" @error('from_address') aria-invalid="true" @enderror><p class="form-help" id="smtp-from-address-help">{{ __('app.smtp_from_address_hint') }}</p></div>
                <div class="notice"><x-icon name="info" /><p>{{ __('app.smtp_disabled_hint') }}</p></div>
            </div>
        </section>
    </div>
    <div class="form-footer settings-submit"><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ __('app.save_settings') }}</button></div>
</form>
@endsection
