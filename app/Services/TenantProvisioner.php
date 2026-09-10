<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;

class TenantProvisioner
{
    public function create(array $data, array $owner): Tenant
    {
        return DB::transaction(function () use ($data, $owner) {
            $tenant = Tenant::create($data);
            $role = new Role(['name' => 'Owner', 'description' => 'Workspace owner', 'permissions' => array_keys(config('saas.tenant_permissions'))]);
            $role->tenant_id = $tenant->id;
            $role->is_owner = true;
            $role->save();
            $user = new User($owner);
            $user->tenant_id = $tenant->id;
            $user->role_id = $role->id;
            $user->save();
            $user->notify(new SystemNotification(['title_key' => 'app.welcome', 'body_key' => 'app.account_ready', 'url' => route('dashboard', absolute: false)]));

            return $tenant;
        });
    }
}
