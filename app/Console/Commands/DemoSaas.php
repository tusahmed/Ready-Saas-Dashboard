<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoSaas extends Command
{
    protected $signature = 'saas:demo';

    protected $description = 'Create local demonstration accounts without replacing existing data';

    public function handle(TenantProvisioner $provisioner): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo data is only allowed in local/testing.');

            return self::FAILURE;
        }
        if (User::whereIn('email', ['admin@orbit.test', 'owner@acme.test', 'owner@nova.test'])->exists() || Tenant::whereIn('slug', ['acme', 'nova'])->exists()) {
            $this->warn('Demo records already exist; nothing was changed.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($provisioner) {
            $root = new User(['name' => 'مدير المنصة', 'email' => 'admin@orbit.test', 'password' => 'OrbitDemo!2026', 'status' => 'active', 'locale' => 'ar']);
            $root->is_super_admin = true;
            $root->save();
            foreach ([['Acme Studio', 'acme', 'growth'], ['Nova Labs', 'nova', 'starter']] as [$name,$slug,$plan]) {
                $tenant = $provisioner->create(['name' => $name, 'slug' => $slug, 'email' => "hello@$slug.test", 'plan' => $plan, 'status' => 'active', 'description' => 'Demo workspace'], ['name' => $slug === 'acme' ? 'أحمد محمد' : 'سارة علي', 'email' => "owner@$slug.test", 'password' => 'OrbitDemo!2026', 'status' => 'active', 'locale' => 'ar']);
                $role = new Role(['name' => 'Viewer', 'description' => 'Read-only team access', 'permissions' => ['users.view', 'roles.view']]);
                $role->tenant_id = $tenant->id;
                $role->save();
                $member = new User(['name' => $slug === 'acme' ? 'عمر خالد' : 'ليلى حسن', 'email' => "member@$slug.test", 'password' => 'OrbitDemo!2026', 'status' => 'active', 'locale' => 'ar', 'job_title' => 'Team member']);
                $member->tenant_id = $tenant->id;
                $member->role_id = $role->id;
                $member->save();
            }
        });
        $this->info('Local demo ready. Password: OrbitDemo!2026');
        $this->table(['Panel', 'Email'], [['Super admin', 'admin@orbit.test'], ['Client owner', 'owner@acme.test'], ['Read-only client', 'member@acme.test'], ['Second client', 'owner@nova.test']]);

        return self::SUCCESS;
    }
}
