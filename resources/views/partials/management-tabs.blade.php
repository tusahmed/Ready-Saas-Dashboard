<nav class="tabs" aria-label="{{ __('app.user_management') }}">
    @if(auth()->user()->canDo('users.view'))
        <a class="tab {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}" @if(request()->routeIs('users.*')) aria-current="page" @endif><x-icon name="users" />{{ __('app.users') }}</a>
    @endif
    @if(auth()->user()->canDo('roles.view'))
        <a class="tab {{ request()->routeIs('roles.*') ? 'is-active' : '' }}" href="{{ route('roles.index') }}" @if(request()->routeIs('roles.*')) aria-current="page" @endif><x-icon name="shield" />{{ __('app.roles_permissions') }}</a>
    @endif
</nav>
