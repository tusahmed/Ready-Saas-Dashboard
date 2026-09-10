<nav class="tabs settings-tabs" aria-label="{{ __('app.settings') }}">
    @if(auth()->user()->canDo('settings.manage'))
        <a class="tab {{ request()->routeIs('settings.general') ? 'is-active' : '' }}" href="{{ route('settings.general') }}" @if(request()->routeIs('settings.general')) aria-current="page" @endif><x-icon name="building" />{{ __('app.general_settings') }}</a>
    @endif
    @if(auth()->user()->isPlatform() && auth()->user()->is_super_admin)
        <a class="tab {{ request()->routeIs('admin.settings.smtp*') ? 'is-active' : '' }}" href="{{ route('admin.settings.smtp') }}" @if(request()->routeIs('admin.settings.smtp*')) aria-current="page" @endif><x-icon name="mail" />{{ __('app.smtp_settings') }}</a>
    @endif
</nav>
