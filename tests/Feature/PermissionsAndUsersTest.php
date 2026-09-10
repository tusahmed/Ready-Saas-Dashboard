<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PermissionsAndUsersTest extends TestCase
{
    public function test_user_without_permissions_cannot_manage_users_roles_or_search_them(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $colleague = $this->user($tenant, attributes: ['name' => 'Restricted Search Person']);
        $role = $this->role($tenant);
        $this->actingAs($user);

        foreach (['users.index', 'users.create', 'roles.index', 'roles.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('users.store'), $this->userPayload())->assertForbidden();
        $this->put(route('users.update', $colleague), $this->userPayload())->assertForbidden();
        $this->delete(route('users.destroy', $colleague))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Forbidden role'])->assertForbidden();
        $this->getJson(route('roles.users', $role))->assertForbidden();
        $this->get(route('search', ['q' => 'Restricted Search']))->assertOk()->assertDontSee($colleague->email);
    }

    public function test_owner_can_create_edit_and_delete_a_colleague(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $role = $this->role($tenant, ['users.view']);
        $payload = $this->userPayload($role, ['phone' => '+201001234567', 'job_title' => 'Support', 'bio' => 'Works with customers']);
        $this->actingAs($owner)->get(route('users.create'))->assertOk();
        $this->post(route('users.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame($role->id, $user->role_id);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->get(route('users.edit', $user))->assertOk()->assertSee($user->email);
        $this->put(route('users.update', $user), array_replace($payload, ['name' => 'Updated colleague', 'password' => '', 'password_confirmation' => '', 'status' => 'inactive']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated colleague', 'status' => 'inactive']);
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->delete(route('users.destroy', $user))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_role_crud_persists_permissions_and_lists_assigned_users(): void
    {
        $tenant = $this->tenant();
        $this->actingAs($this->owner($tenant));
        $this->post(route('roles.store'), ['name' => 'Support team', 'description' => 'Customer support', 'permissions' => ['users.view']])->assertRedirect()->assertSessionHasNoErrors();
        $role = Role::where('tenant_id', $tenant->id)->where('name', 'Support team')->firstOrFail();
        $this->assertSame(['users.view'], $role->permissions);
        $this->put(route('roles.update', $role), ['name' => 'Support lead', 'description' => 'Lead role', 'permissions' => ['users.view', 'users.create']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['users.view', 'users.create'], $role->fresh()->permissions);
        $colleague = $this->user($tenant, $role);
        $this->getJson(route('roles.users', $role))->assertOk()->assertJsonFragment(['email' => $colleague->email]);
        $this->delete(route('users.destroy', $colleague))->assertRedirect();
        $this->delete(route('roles.destroy', $role))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_tenant_cannot_create_platform_permission_or_an_owner_role(): void
    {
        $tenant = $this->tenant();
        $this->actingAs($this->owner($tenant));
        $this->postJson(route('roles.store'), ['name' => 'Elevated', 'permissions' => ['clients.delete', 'notifications.send']])->assertStatus(422);
        $this->assertDatabaseMissing('roles', ['tenant_id' => $tenant->id, 'name' => 'Elevated']);
        $this->post(route('roles.store'), ['name' => 'Pretend owner', 'is_owner' => true, 'tenant_id' => null, 'permissions' => ['users.view']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('roles', ['tenant_id' => $tenant->id, 'name' => 'Pretend owner', 'is_owner' => false]);
    }

    public function test_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $tenant = $this->tenant();
        $managerRole = $this->role($tenant, ['roles.view', 'roles.create', 'roles.update', 'users.view', 'users.create', 'users.update']);
        $manager = $this->user($tenant, $managerRole);
        $strongRole = $this->role($tenant, ['users.view', 'users.delete']);
        $this->actingAs($manager);

        $response = $this->postJson(route('roles.store'), ['name' => 'Escalated role', 'permissions' => ['users.delete']]);
        $this->assertContains($response->status(), [403, 422]);
        $this->assertDatabaseMissing('roles', ['name' => 'Escalated role']);
        $response = $this->putJson(route('roles.update', $managerRole), ['name' => $managerRole->name, 'permissions' => ['users.delete']]);
        $this->assertContains($response->status(), [403, 422]);
        $this->assertNotContains('users.delete', $managerRole->fresh()->permissions);
        $response = $this->postJson(route('users.store'), $this->userPayload($strongRole));
        $this->assertContains($response->status(), [403, 422]);
    }

    public function test_tenant_owner_role_is_immutable_and_owner_is_protected(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $originalRole = $owner->role;
        $ordinaryRole = $this->role($tenant, ['users.view']);
        $this->actingAs($owner);

        $response = $this->putJson(route('roles.update', $originalRole), ['name' => 'Rewritten owner', 'permissions' => []]);
        $this->assertContains($response->status(), [403, 422]);
        $response = $this->deleteJson(route('roles.destroy', $originalRole));
        $this->assertContains($response->status(), [403, 422]);
        $response = $this->deleteJson(route('users.destroy', $owner));
        $this->assertContains($response->status(), [403, 422]);
        $response = $this->putJson(route('users.update', $owner), $this->userPayload($ordinaryRole, ['email' => $owner->email, 'status' => 'inactive']));
        $this->assertContains($response->status(), [403, 422]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role_id' => $originalRole->id, 'status' => 'active']);
        $this->assertDatabaseHas('roles', ['id' => $originalRole->id, 'name' => $originalRole->name, 'is_owner' => true]);
    }

    public function test_platform_staff_cannot_modify_root_account(): void
    {
        $root = $this->rootUser();
        $role = $this->role(permissions: array_keys(config('saas.permissions')));
        $staff = $this->user(role: $role);
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $response = $this->putJson(route('users.update', $root), $this->userPayload($role, ['email' => $root->email]));
        $this->assertContains($response->status(), [403, 422]);
        $response = $this->deleteJson(route('users.destroy', $root));
        $this->assertContains($response->status(), [403, 422]);
        $this->assertDatabaseHas('users', ['id' => $root->id, 'is_super_admin' => true]);
    }

    public function test_duplicate_email_is_rejected_and_user_inputs_are_escaped(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $role = $this->role($tenant, ['users.view']);
        $existing = $this->user($tenant, $role, ['name' => '<script>alert("xss")</script>']);
        $this->actingAs($owner)->post(route('users.store'), $this->userPayload($role, ['email' => $existing->email]))->assertSessionHasErrors('email');
        $this->get(route('users.index'))->assertOk()->assertDontSee('<script>alert("xss")</script>', false)->assertSee($existing->name);
    }

    public function test_create_only_user_is_redirected_to_an_accessible_dashboard_after_saving(): void
    {
        $tenant = $this->tenant();
        $role = $this->role($tenant, ['users.create']);
        $creator = $this->user($tenant, $role);
        $payload = $this->userPayload();
        $response = $this->actingAs($creator)->post(route('users.store'), $payload);
        $response->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        $this->followRedirects($response)->assertOk();
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'tenant_id' => $tenant->id]);
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_removing_own_role_view_permission_redirects_to_accessible_dashboard(): void
    {
        $tenant = $this->tenant();
        $role = $this->role($tenant, ['roles.view', 'roles.update']);
        $manager = $this->user($tenant, $role);
        $response = $this->actingAs($manager)->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => []]);
        $response->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        $this->followRedirects($response)->assertOk();
        $this->assertSame([], $role->fresh()->permissions);
        $this->actingAs($manager->fresh())->get(route('roles.index'))->assertForbidden();
    }

    public function test_admin_changing_colleague_password_revokes_old_reset_link(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $role = $this->role($tenant, ['users.view']);
        $colleague = $this->user($tenant, $role);
        $token = Password::createToken($colleague);
        $this->actingAs($owner)->put(route('users.update', $colleague), $this->userPayload($role, [
            'email' => $colleague->email, 'password' => 'AdminChanged!456', 'password_confirmation' => 'AdminChanged!456',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('AdminChanged!456', $colleague->fresh()->password));
        $this->assertFalse(Password::tokenExists($colleague, $token));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $colleague->email]);
    }

    public function test_unsupported_password_bytes_are_rejected_without_hashing_errors_or_user_creation(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $role = $this->role($tenant, ['users.view']);
        $this->actingAs($owner);
        foreach (["SafePassword\0!123", str_repeat('A', 70).'a12', str_repeat('é', 36).'A1a'] as $password) {
            $payload = $this->userPayload($role, ['password' => $password, 'password_confirmation' => $password]);
            $this->postJson(route('users.store'), $payload)->assertStatus(422)->assertJsonValidationErrors('password');
            $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        }
    }
}
