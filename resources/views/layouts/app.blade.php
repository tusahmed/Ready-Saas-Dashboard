@php
    $currentUser = auth()->user();
    $isPlatform = $currentUser->isPlatform();
    $languages = ['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español'];
    $dashboardRoute = $isPlatform ? 'admin.dashboard' : 'client.dashboard';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-layout="{{ $currentUser->layout === 'horizontal' ? 'horizontal' : 'vertical' }}" data-theme="{{ $currentUser->theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('app.dashboard')) · Orbit</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}?v={{ filemtime(public_path('assets/fonts.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
    @stack('styles')
</head>
<body class="app-body">
    <a class="skip-link" href="#main-content">{{ __('app.skip_to_content') }}</a>
    <div class="sidebar-overlay" data-sidebar-close hidden></div>
    <aside class="sidebar" id="sidebar" aria-label="{{ __('app.main_menu') }}">
        <div class="sidebar-brand"><a href="{{ route($dashboardRoute) }}" aria-label="{{ $workspaceBranding->business_name }}"><x-brand :name="$workspaceBranding->business_name" :logo="$workspaceBranding->logo_url" /></a><button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="{{ __('app.close_menu') }}"><x-icon name="close" /></button></div>
        <div class="workspace-switcher"><span class="workspace-icon"><x-icon name="{{ $isPlatform ? 'layers' : 'building' }}" /></span><div><span class="workspace-name">{{ $workspaceBranding->business_name }}</span><span class="workspace-caption">{{ $isPlatform ? __('app.platform_admin') : __('app.client_workspace') }}</span></div><x-icon name="shield" class="workspace-shield" /></div>
        <div class="sidebar-scroll">
            <nav class="sidebar-nav" aria-label="{{ __('app.main_menu') }}">@include('partials.navigation', ['navPrefix' => 'sidebar'])</nav>
        </div>
        <a class="sidebar-customize" href="{{ route('profile.edit') }}#appearance-form"><x-icon name="dashboard" /><span>{{ __('app.customize_workspace') }}</span><x-icon name="arrow-up-right" /></a>
    </aside>
    <div class="app-shell">
        <header class="topbar">
            <div class="topbar-start"><a class="topbar-brand" href="{{ route($dashboardRoute) }}" aria-label="{{ $workspaceBranding->business_name }}"><x-brand :name="$workspaceBranding->business_name" :logo="$workspaceBranding->logo_url" /></a><button type="button" class="icon-button mobile-menu" data-sidebar-open aria-label="{{ __('app.open_menu') }}" aria-controls="sidebar" aria-expanded="false"><x-icon name="menu" /></button></div>
            <div class="topbar-actions">
                <form action="{{ route('search') }}" method="GET" class="global-search" role="search"><x-icon name="search" /><input type="search" id="global-search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('app.search_placeholder') }}" aria-label="{{ __('app.search') }}" maxlength="100"><kbd aria-hidden="true">⌘ K</kbd></form>
                <form action="{{ route('preferences.update') }}" method="POST" class="locale-form">@csrf @method('PATCH')<x-icon name="globe" /><label class="sr-only" for="locale">{{ __('app.language') }}</label><select id="locale" name="locale" data-locale-select>@foreach($languages as $code => $label)<option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>@endforeach</select><noscript><button type="submit" class="btn btn-sm">{{ __('app.save') }}</button></noscript></form>
                <button type="button" class="icon-button theme-toggle" data-theme-toggle aria-label="{{ __('app.appearance') }}" title="{{ __('app.appearance') }}"><span class="theme-sun"><x-icon name="sun" /></span><span class="theme-moon"><x-icon name="moon" /></span></button>
                <div class="dropdown notification-dropdown"><button type="button" class="icon-button notification-trigger" data-dropdown-trigger aria-expanded="false" aria-controls="notification-menu" aria-label="{{ __('app.notifications') }}"><x-icon name="bell" /><span class="notification-badge" data-notification-count hidden>0</span></button><div class="dropdown-panel notification-panel" id="notification-menu" hidden><div class="dropdown-heading"><strong>{{ __('app.notifications') }}</strong><button class="text-button" type="button" data-read-all>{{ __('app.mark_all_read') }}</button></div><div class="notification-list" data-notification-list><div class="dropdown-empty"><x-icon name="bell" /><p>{{ __('app.loading') }}</p></div></div><a class="dropdown-footer" href="{{ route('notifications.index') }}">{{ __('app.view_all_notifications') }}<x-icon name="arrow-right" /></a></div></div>
                <span class="topbar-separator" aria-hidden="true"></span>
                <div class="dropdown account-dropdown"><button type="button" class="account-trigger" data-dropdown-trigger aria-expanded="false" aria-controls="account-menu" aria-label="{{ __('app.account') }}"><span class="avatar">{{ $currentUser->initials() }}</span><span class="account-trigger-info"><strong>{{ $currentUser->name }}</strong><span>{{ $currentUser->is_super_admin ? __('app.platform_admin') : ($currentUser->role?->name ?? __('app.no_role')) }}</span></span><x-icon name="chevron-down" /></button><div class="dropdown-panel account-panel" id="account-menu" hidden><div class="account-menu-header"><span>{{ __('app.signed_in_as') }}</span><strong>{{ $currentUser->name }}</strong><small dir="ltr">{{ $currentUser->email }}</small></div><a class="dropdown-item" href="{{ route('profile.edit') }}"><x-icon name="user" />{{ __('app.profile') }}</a><button class="dropdown-item" type="button" data-theme-toggle><x-icon name="sun" />{{ __('app.appearance') }}<span class="keyboard-hint" data-theme-label></span></button><div class="dropdown-divider"></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><x-icon name="logout" />{{ __('app.logout') }}</button></form></div></div>
            </div>
        </header>
        <nav class="horizontal-nav" aria-label="{{ __('app.main_menu') }}">@include('partials.navigation', ['navPrefix' => 'horizontal'])</nav>
        <main class="main-content" id="main-content" tabindex="-1"><x-alerts />@yield('content')</main>
        <footer class="app-footer"><span>© {{ date('Y') }} Orbit. <span class="footer-extra">{{ __('app.powered_by') }}</span></span><span><span class="status-dot"></span>{{ __('app.secure_workspace') }}</span></footer>
    </div>
    <div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="false"></div>
    <dialog class="modal" id="role-users-dialog" aria-labelledby="role-users-title"><div class="modal-header"><div><span class="eyebrow">{{ __('app.roles_permissions') }}</span><h2 id="role-users-title">{{ __('app.role_members') }}</h2></div><button type="button" class="icon-button" data-dialog-close aria-label="{{ __('app.close') }}"><x-icon name="close" /></button></div><div class="modal-body" data-role-users-content></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dialog-close>{{ __('app.close') }}</button></div></dialog>
    <dialog class="modal modal-sm" id="confirm-dialog" aria-labelledby="confirm-title"><div class="modal-header"><span class="confirm-icon"><x-icon name="trash" /></span><button type="button" class="icon-button" data-dialog-close aria-label="{{ __('app.close') }}"><x-icon name="close" /></button></div><div class="modal-body"><h2 id="confirm-title">{{ __('app.confirm_delete') }}</h2><p class="muted" data-confirm-message></p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dialog-close>{{ __('app.cancel') }}</button><button type="button" class="btn btn-danger" data-confirm-submit>{{ __('app.delete') }}</button></div></dialog>
    @php
    $browserConfig = ['preferencesUrl' => route('preferences.update'), 'feedUrl' => route('notifications.feed'), 'readUrl' => route('notifications.read', '__ID__'), 'readAllUrl' => route('notifications.read-all'), 'userId' => $currentUser->id, 'locale' => app()->getLocale(), 'strings' => ['success' => __('app.success'), 'error' => __('app.request_failed'), 'loading' => __('app.loading'), 'noNotifications' => __('app.no_notifications'), 'notification' => __('app.notification'), 'justNow' => __('app.just_now'), 'close' => __('app.close'), 'roleMembers' => __('app.role_members'), 'noRoleUsers' => __('app.no_role_users'), 'active' => __('app.active'), 'inactive' => __('app.inactive'), 'darkMode' => __('app.dark_mode'), 'lightMode' => __('app.light_mode')]];
@endphp
<script type="application/json" id="app-config">@json($browserConfig)</script>
    <script src="{{ asset('assets/app.js') }}?v={{ filemtime(public_path('assets/app.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
