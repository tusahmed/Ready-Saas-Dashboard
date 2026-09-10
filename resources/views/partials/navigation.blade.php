@php
    $navUser = auth()->user();
    $navGroups = [
        ['key' => 'overview', 'label' => __('app.nav_overview'), 'icon' => 'dashboard', 'items' => array_filter([
            ['route' => $navUser->isPlatform() ? 'admin.dashboard' : 'client.dashboard', 'pattern' => $navUser->isPlatform() ? 'admin.dashboard' : 'client.dashboard', 'label' => $navUser->isPlatform() ? __('app.board') : __('app.dashboard'), 'icon' => 'chart'],
            $navUser->isPlatform() && $navUser->canDo('clients.view') ? ['route' => 'admin.clients.index', 'pattern' => 'admin.clients.*', 'label' => __('app.clients'), 'icon' => 'building'] : null,
        ])],
        ['key' => 'management', 'label' => __('app.user_management'), 'icon' => 'users', 'items' => array_filter([
            $navUser->canDo('users.view') ? ['route' => 'users.index', 'pattern' => 'users.*', 'label' => __('app.users'), 'icon' => 'user'] : null,
            $navUser->canDo('roles.view') ? ['route' => 'roles.index', 'pattern' => 'roles.*', 'label' => __('app.roles_permissions'), 'icon' => 'shield'] : null,
        ])],
        ['key' => 'workspace', 'label' => __('app.workspace'), 'icon' => 'settings', 'items' => array_filter([
            ['route' => 'notifications.index', 'pattern' => 'notifications.*', 'label' => __('app.notifications'), 'icon' => 'bell'],
            $navUser->isPlatform() && $navUser->canDo('notifications.send') ? ['route' => 'admin.notifications.create', 'pattern' => 'admin.notifications.*', 'label' => __('app.send_notification'), 'icon' => 'send'] : null,
            ['route' => 'profile.edit', 'pattern' => 'profile.*', 'label' => __('app.profile'), 'icon' => 'user'],
        ])],
        ['key' => 'settings', 'label' => __('app.settings'), 'icon' => 'settings', 'items' => array_filter([
            $navUser->canDo('settings.manage') ? ['route' => 'settings.general', 'pattern' => 'settings.general', 'label' => __('app.general_settings'), 'icon' => 'building'] : null,
            $navUser->isPlatform() && $navUser->is_super_admin ? ['route' => 'admin.settings.smtp', 'pattern' => 'admin.settings.smtp*', 'label' => __('app.smtp_settings'), 'icon' => 'mail'] : null,
        ])],
    ];
@endphp
@foreach($navGroups as $navGroup)
    @if(count($navGroup['items']))
        @php
            $groupActive = collect($navGroup['items'])->contains(fn ($item) => request()->routeIs($item['pattern']));
            $groupOpen = $navPrefix === 'sidebar' && $groupActive;
            $groupId = $navPrefix.'-'.$navGroup['key'];
        @endphp
        <div class="nav-group {{ $groupActive ? 'is-active' : '' }}" data-nav-group data-nav-key="{{ $navGroup['key'] }}">
            <button class="nav-group-toggle" type="button" data-nav-toggle aria-expanded="{{ $groupOpen ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
                <x-icon :name="$navGroup['icon']" /><span>{{ $navGroup['label'] }}</span><x-icon name="chevron-down" class="nav-chevron" />
            </button>
            <div class="nav-submenu" id="{{ $groupId }}" data-nav-submenu @if(!$groupOpen) hidden @endif>
                @foreach($navGroup['items'] as $navItem)
                    <a class="nav-sublink {{ request()->routeIs($navItem['pattern']) ? 'is-active' : '' }}" href="{{ route($navItem['route']) }}" @if(request()->routeIs($navItem['pattern'])) aria-current="page" @endif>
                        <span class="nav-item-icon"><x-icon :name="$navItem['icon']" /></span><span>{{ $navItem['label'] }}</span>
                        @if($navItem['route'] === 'notifications.index')<span class="nav-count" data-notification-count hidden>0</span>@endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endforeach
