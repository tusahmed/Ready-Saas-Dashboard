<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    private function clientPayload(array $attributes = []): array
    {
        return array_replace([
            'name' => 'Acme Company', 'slug' => 'acme-company', 'email' => 'contact@acme.test',
            'phone' => '+201001234567', 'website' => 'https://example.test', 'description' => 'Client workspace',
            'plan' => 'growth', 'status' => 'active', 'owner_name' => 'Acme Owner',
            'owner_email' => 'owner@acme.test', 'owner_password' => 'OwnerPassword!123',
            'owner_password_confirmation' => 'OwnerPassword!123',
        ], $attributes);
    }

    public function test_root_creates_client_with_owner_role_and_hashed_owner_password(): void
    {
        $root = $this->rootUser();
        $this->actingAs($root)->get(route('admin.clients.create'))->assertOk();
        $this->post(route('admin.clients.store'), $this->clientPayload())->assertRedirect()->assertSessionHasNoErrors();
        $client = Tenant::where('slug', 'acme-company')->firstOrFail();
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $this->assertSame($client->id, $owner->tenant_id);
        $this->assertFalse($owner->is_super_admin);
        $this->assertTrue($owner->role->is_owner);
        $this->assertSame($client->id, $owner->role->tenant_id);
        $this->assertTrue($owner->canDo('users.create'));
        $this->assertFalse($owner->canDo('clients.create'));
        $this->assertTrue(Hash::check('OwnerPassword!123', $owner->password));
        $this->get(route('admin.clients.index'))->assertOk()->assertSee($client->name);
        $this->get(route('admin.clients.show', $client))->assertOk()->assertSee($owner->email);
        $this->get(route('admin.clients.edit', $client))->assertOk();
        $this->put(route('admin.clients.update', $client), $this->clientPayload(['name' => 'Acme Updated', 'plan' => 'enterprise']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenants', ['id' => $client->id, 'name' => 'Acme Updated', 'plan' => 'enterprise']);
    }

    public function test_invalid_owner_does_not_leave_partial_client_or_role(): void
    {
        $root = $this->rootUser();
        $existing = $this->user();
        $this->actingAs($root)->post(route('admin.clients.store'), $this->clientPayload(['owner_email' => $existing->email]))->assertSessionHasErrors('owner_email');
        $this->assertDatabaseMissing('tenants', ['slug' => 'acme-company']);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_client_validation_does_not_flash_owner_passwords_to_session(): void
    {
        $this->actingAs($this->rootUser())
            ->from(route('admin.clients.create'))
            ->post(route('admin.clients.store'), $this->clientPayload(['name' => '']))
            ->assertRedirect(route('admin.clients.create'))
            ->assertSessionHasErrors('name')
            ->assertSessionHas('_old_input.owner_name', 'Acme Owner')
            ->assertSessionMissing('_old_input.owner_password')
            ->assertSessionMissing('_old_input.owner_password_confirmation');
        $this->get(route('admin.clients.create'))->assertDontSee('OwnerPassword!123');
    }

    public function test_provisioning_rolls_back_if_owner_creation_fails_mid_transaction(): void
    {
        $root = $this->rootUser();
        User::creating(function (User $user) {
            if ($user->email === 'owner@acme.test') {
                throw new \RuntimeException('Simulated owner insert failure');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($root)->post(route('admin.clients.store'), $this->clientPayload());
            $this->fail('Expected the simulated owner insertion to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated owner insert failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('tenants', ['slug' => 'acme-company']);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseMissing('users', ['email' => 'owner@acme.test']);
    }

    public function test_deleting_a_client_removes_its_users_and_roles_without_touching_others(): void
    {
        $root = $this->rootUser();
        $first = $this->tenant();
        $firstOwner = $this->owner($first);
        $second = $this->tenant();
        $secondOwner = $this->owner($second);

        $this->actingAs($root)->delete(route('admin.clients.destroy', $first))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('tenants', ['id' => $first->id]);
        $this->assertDatabaseMissing('users', ['id' => $firstOwner->id]);
        $this->assertDatabaseMissing('roles', ['id' => $firstOwner->role_id]);
        $this->assertDatabaseHas('tenants', ['id' => $second->id]);
        $this->assertDatabaseHas('users', ['id' => $secondOwner->id]);
        $this->assertDatabaseHas('users', ['id' => $root->id]);
    }

    public function test_platform_permissions_restrict_client_management(): void
    {
        $role = $this->role(permissions: ['clients.view']);
        $staff = $this->user(role: $role);
        $client = $this->tenant();
        $this->actingAs($staff)->get(route('admin.clients.index'))->assertOk();
        $this->get(route('admin.clients.show', $client))->assertOk();
        $this->get(route('admin.clients.create'))->assertForbidden();
        $this->post(route('admin.clients.store'), $this->clientPayload())->assertForbidden();
        $this->put(route('admin.clients.update', $client), $this->clientPayload())->assertForbidden();
        $this->delete(route('admin.clients.destroy', $client))->assertForbidden();
    }

    public function test_board_statistics_are_derived_from_actual_clients(): void
    {
        $root = $this->rootUser();
        $this->tenant(['status' => 'active', 'plan' => 'starter']);
        $this->tenant(['status' => 'suspended', 'plan' => 'enterprise']);
        $this->actingAs($root)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['clients'] === 2 && $stats['active_clients'] === 1)
            ->assertViewHas('planCounts', fn ($counts) => $counts['starter'] === 1 && $counts['enterprise'] === 1);
    }

    public function test_platform_staff_activity_is_limited_to_their_own_actions(): void
    {
        $root = $this->rootUser();
        $role = $this->role(permissions: ['clients.view']);
        $staff = $this->user(role: $role);
        Activity::create(['tenant_id' => null, 'user_id' => $root->id, 'action' => 'users.created', 'description' => 'Private platform user operation']);
        Activity::create(['tenant_id' => null, 'user_id' => $staff->id, 'action' => 'profile.updated', 'description' => 'Own visible operation']);
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->assertSee('Own visible operation')->assertDontSee('Private platform user operation')
            ->assertViewHas('recentActivity', fn ($activities) => $activities->count() === 1 && $activities->every(fn ($activity) => $activity->user_id === $staff->id));
        $this->actingAs($root)->get(route('admin.dashboard'))->assertOk()->assertSee('Private platform user operation')->assertSee('Own visible operation');
    }
}
