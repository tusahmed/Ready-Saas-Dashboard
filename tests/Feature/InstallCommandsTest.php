<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallCommandsTest extends TestCase
{
    public function test_install_creates_platform_root_with_hashed_password(): void
    {
        $this->artisan('saas:install', ['--email' => 'new-admin@example.test', '--name' => 'Production Admin', '--password' => 'SecureAdmin!2026'])->assertSuccessful();
        $user = User::where('email', 'new-admin@example.test')->firstOrFail();
        $this->assertNull($user->tenant_id);
        $this->assertNull($user->role_id);
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue(Hash::check('SecureAdmin!2026', $user->password));
    }

    public function test_install_cannot_overwrite_an_existing_user_or_accept_a_weak_password(): void
    {
        $existing = $this->user($this->tenant());
        $this->artisan('saas:install', ['--email' => $existing->email, '--name' => 'Hijacked', '--password' => 'SecureAdmin!2026'])->assertFailed();
        $this->assertFalse($existing->fresh()->is_super_admin);
        $this->assertSame($existing->name, $existing->fresh()->name);
        $this->artisan('saas:install', ['--email' => 'new-admin@example.test', '--name' => 'Admin', '--password' => 'weak'])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.test']);
    }

    public function test_demo_command_is_idempotent_and_production_is_rejected(): void
    {
        $this->artisan('saas:demo')->assertSuccessful();
        $this->assertDatabaseCount('tenants', 2);
        $user = User::where('email', 'admin@orbit.test')->firstOrFail();
        $user->update(['name' => 'Keep this edit']);
        $userCount = User::count();
        $this->artisan('saas:demo')->assertSuccessful();
        $this->assertDatabaseCount('tenants', 2);
        $this->assertDatabaseCount('users', $userCount);
        $this->assertSame('Keep this edit', $user->fresh()->name);
        $this->app->instance('env', 'production');
        $this->artisan('saas:demo')->assertFailed();
        $this->app->instance('env', 'testing');
    }
}
