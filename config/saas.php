<?php

$tenant = [];
foreach (['users', 'roles'] as $resource) {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        $tenant["$resource.$action"] = "app.perm_{$resource}_{$action}";
    }
}
$platform = [];
$tenant['settings.manage'] = 'app.perm_settings_manage';
foreach (['view', 'create', 'update', 'delete'] as $action) {
    $platform["clients.$action"] = "app.perm_clients_{$action}";
}

return [
    'locales' => ['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español'],
    'tenant_permissions' => $tenant,
    'permissions' => $tenant + $platform + ['notifications.send' => 'app.perm_notifications_send'],
];
