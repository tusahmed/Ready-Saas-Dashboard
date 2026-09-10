<?php

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();

        // Stop before RefreshDatabase can ever touch the development database.
        if ($app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.database') !== 'orbit_saas_test') {
            throw new \RuntimeException('Tests require the isolated MySQL database orbit_saas_test. Run php artisan config:clear first.');
        }

        return $app;
    }

    protected function tenant(array $attributes = []): Tenant
    {
        $suffix = Str::lower(Str::random(10));

        return Tenant::create(array_replace([
            'name' => 'Workspace '.$suffix,
            'slug' => 'workspace-'.$suffix,
            'email' => $suffix.'@example.test',
            'plan' => 'starter',
            'status' => 'active',
        ], $attributes));
    }

    protected function role(?Tenant $tenant = null, array $permissions = [], array $attributes = []): Role
    {
        return Role::forceCreate(array_replace([
            'tenant_id' => $tenant?->id,
            'name' => 'Role '.Str::random(8),
            'permissions' => $permissions,
            'is_owner' => false,
        ], $attributes));
    }

    protected function user(?Tenant $tenant = null, ?Role $role = null, array $attributes = []): User
    {
        return User::forceCreate(array_replace([
            'tenant_id' => $tenant?->id,
            'role_id' => $role?->id,
            'name' => 'Test User '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => 'SafePassword!123',
            'status' => 'active',
            'is_super_admin' => false,
            'locale' => 'en',
            'theme' => 'light',
        ], $attributes));
    }

    protected function rootUser(): User
    {
        return $this->user(attributes: ['is_super_admin' => true]);
    }

    protected function owner(Tenant $tenant): User
    {
        $permissions = array_values(array_filter(array_keys(config('saas.permissions')), fn ($key) => ! str_starts_with($key, 'clients.') && $key !== 'notifications.send'));
        $role = $this->role($tenant, $permissions, ['is_owner' => true, 'name' => 'Owner']);

        return $this->user($tenant, $role);
    }

    protected function userPayload(?Role $role = null, array $attributes = []): array
    {
        return array_replace([
            'name' => 'New colleague',
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => 'SafePassword!123',
            'password_confirmation' => 'SafePassword!123',
            'status' => 'active',
            'role_id' => $role?->id,
        ], $attributes);
    }
}
