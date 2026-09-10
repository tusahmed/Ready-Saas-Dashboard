@extends('layouts.app')
@section('title', __('app.profile'))
@section('content')
<div class="page-heading"><div><h1 class="page-title">{{ __('app.my_profile') }}</h1><p class="page-description">{{ __('app.profile_description') }}</p></div></div>
<div class="split-grid form-layout">
    <div class="form-main">
        @include('partials.appearance')
        <form action="{{ route('profile.update') }}" method="POST" class="card">@csrf @method('PATCH')
            <div class="card-header"><div><h2 class="card-title">{{ __('app.personal_information') }}</h2><p class="card-subtitle">{{ __('app.personal_information_description') }}</p></div><x-icon name="user" /></div>
            <div class="card-body form-grid">
                <div class="form-group"><label class="form-label" for="name">{{ __('app.full_name') }} <span class="required">*</span></label><input class="form-input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"></div>
                <div class="form-group"><label class="form-label" for="email">{{ __('app.email') }} <span class="required">*</span></label><input class="form-input" id="email" name="email" type="email" dir="ltr" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email"></div>
                <div class="form-group"><label class="form-label" for="phone">{{ __('app.phone') }}</label><input class="form-input" id="phone" name="phone" type="tel" dir="ltr" value="{{ old('phone', $user->phone) }}" maxlength="40" autocomplete="tel"></div>
                <div class="form-group"><label class="form-label" for="job_title">{{ __('app.job_title') }}</label><input class="form-input" id="job_title" name="job_title" value="{{ old('job_title', $user->job_title) }}" maxlength="120" autocomplete="organization-title"></div>
                <div class="form-group full-width"><label class="form-label" for="bio">{{ __('app.bio') }}</label><textarea class="form-textarea" id="bio" name="bio" rows="4" maxlength="2000">{{ old('bio', $user->bio) }}</textarea><p class="form-help">{{ __('app.bio_hint') }}</p></div>
                <div class="form-group full-width"><label class="form-label" for="profile_current_password">{{ __('app.current_password') }}</label><x-password-input id="profile_current_password" name="current_password" autocomplete="current-password" /><p class="form-help">{{ __('app.email_password_confirmation_hint') }}</p></div>
            </div><div class="card-footer"><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ __('app.save_changes') }}</button></div>
        </form>
        <form action="{{ route('profile.password') }}" method="POST" class="card">@csrf @method('PUT')
            <div class="card-header"><div><h2 class="card-title">{{ __('app.change_password') }}</h2><p class="card-subtitle">{{ __('app.password_requirements') }}</p></div><x-icon name="lock" /></div>
            <div class="card-body form-grid"><div class="form-group full-width"><label class="form-label" for="current_password">{{ __('app.current_password') }}</label><x-password-input id="current_password" name="current_password" required autocomplete="current-password" /></div><div class="form-group"><label class="form-label" for="password">{{ __('app.new_password') }}</label><x-password-input id="password" name="password" minlength="12" required autocomplete="new-password" /></div><div class="form-group"><label class="form-label" for="password_confirmation">{{ __('app.confirm_password') }}</label><x-password-input id="password_confirmation" name="password_confirmation" minlength="12" required autocomplete="new-password" /></div></div>
            <div class="card-footer"><button class="btn btn-secondary" type="submit"><x-icon name="lock" />{{ __('app.update_password') }}</button></div>
        </form>
    </div>
    <aside class="form-aside"><section class="card profile-card"><div class="profile-cover"></div><div class="card-body"><span class="avatar avatar-lg">{{ $user->initials() }}</span><h2>{{ $user->name }}</h2><p class="muted">{{ $user->job_title ?: __('app.team_member') }}</p><span class="badge badge-primary">{{ $user->is_super_admin ? __('app.super_admin') : ($user->role?->name ?? __('app.no_role')) }}</span><dl class="detail-list"><div><dt>{{ __('app.workspace') }}</dt><dd>{{ $user->tenant?->name ?? __('app.platform_workspace') }}</dd></div><div><dt>{{ __('app.joined') }}</dt><dd>{{ $user->created_at->translatedFormat('d M Y') }}</dd></div><div><dt>{{ __('app.status') }}</dt><dd><span class="badge badge-success">{{ __('app.'.$user->status) }}</span></dd></div></dl></div></section>

    </aside>
</div>
@endsection
