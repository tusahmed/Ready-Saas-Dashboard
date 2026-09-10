<?php

namespace Tests\Feature;

use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_tenant_lists_search_and_role_popup_never_expose_another_tenant(): void
    {
        $first = $this->tenant();
        $second = $this->tenant();
        $owner = $this->owner($first);
        $ownRole = $this->role($first, ['users.view'], ['name' => 'Visible Alpha Role']);
        $ownUser = $this->user($first, $ownRole, ['name' => 'SharedLookup Visible']);
        $hiddenRole = $this->role($second, ['users.view'], ['name' => 'Private Beta Role']);
        $hiddenUser = $this->user($second, $hiddenRole, ['name' => 'SharedLookup Secret']);
        $platform = $this->user(attributes: ['name' => 'SharedLookup Platform']);

        $this->actingAs($owner)->get(route('users.index'))->assertOk()->assertSee($ownUser->email)->assertDontSee($hiddenUser->email)->assertDontSee($platform->email);
        $this->get(route('roles.index'))->assertOk()->assertSee($ownRole->name)->assertDontSee($hiddenRole->name);
        $this->get(route('search', ['q' => 'SharedLookup']))->assertOk()->assertSee($ownUser->email)->assertDontSee($hiddenUser->email)->assertDontSee($platform->email);
        $this->getJson(route('roles.users', $ownRole))->assertOk()->assertJsonFragment(['email' => $ownUser->email])->assertJsonMissing(['email' => $hiddenUser->email]);
        $this->getJson(route('roles.users', $hiddenRole))->assertNotFound();
    }

    public function test_tenant_cannot_read_update_or_delete_foreign_users_and_roles(): void
    {
        $first = $this->tenant();
        $second = $this->tenant();
        $owner = $this->owner($first);
        $foreignRole = $this->role($second, ['users.view']);
        $foreignUser = $this->user($second, $foreignRole);

        $this->actingAs($owner)->get(route('users.edit', $foreignUser))->assertNotFound();
        $this->put(route('users.update', $foreignUser), $this->userPayload($foreignRole))->assertNotFound();
        $this->delete(route('users.destroy', $foreignUser))->assertNotFound();
        $this->get(route('roles.edit', $foreignRole))->assertNotFound();
        $this->put(route('roles.update', $foreignRole), ['name' => 'Hijacked', 'permissions' => ['users.view']])->assertNotFound();
        $this->delete(route('roles.destroy', $foreignRole))->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $foreignUser->id, 'email' => $foreignUser->email]);
        $this->assertDatabaseHas('roles', ['id' => $foreignRole->id, 'name' => $foreignRole->name]);
    }

    public function test_platform_user_management_is_scoped_to_platform_even_for_root(): void
    {
        $root = $this->rootUser();
        $tenant = $this->tenant();
        $role = $this->role($tenant);
        $user = $this->user($tenant, $role);
        $staff = $this->user();

        $this->actingAs($root)->get(route('users.index'))->assertOk()->assertSee($staff->email)->assertDontSee($user->email);
        $this->get(route('users.edit', $user))->assertNotFound();
        $this->getJson(route('roles.users', $role))->assertNotFound();
    }

    public function test_submitted_tenant_and_superadmin_fields_cannot_escape_scope(): void
    {
        $first = $this->tenant();
        $second = $this->tenant();
        $owner = $this->owner($first);
        $role = $this->role($first, ['users.view']);
        $payload = $this->userPayload($role, ['tenant_id' => $second->id, 'is_super_admin' => true]);

        $this->actingAs($owner)->post(route('users.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'tenant_id' => $first->id, 'is_super_admin' => false]);
    }

    public function test_cross_tenant_role_assignment_is_rejected_on_create_and_update(): void
    {
        $first = $this->tenant();
        $second = $this->tenant();
        $owner = $this->owner($first);
        $foreignRole = $this->role($second, ['users.view']);
        $ownRole = $this->role($first, ['users.view']);
        $colleague = $this->user($first, $ownRole);

        $this->actingAs($owner)->post(route('users.store'), $this->userPayload($foreignRole))->assertSessionHasErrors('role_id');
        $this->put(route('users.update', $colleague), $this->userPayload($foreignRole, ['email' => $colleague->email]))->assertSessionHasErrors('role_id');
        $this->assertSame($ownRole->id, $colleague->fresh()->role_id);
    }
}
