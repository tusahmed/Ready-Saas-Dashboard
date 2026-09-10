<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_layout_is_persisted_for_the_current_user_only(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant);
        $colleague = $this->user($tenant);
        $this->assertSame('vertical', $actor->fresh()->layout);

        $this->actingAs($actor)->patchJson(route('preferences.update'), [
            'layout' => 'horizontal', 'user_id' => $colleague->id, 'is_super_admin' => true,
        ])->assertOk()->assertJsonPath('layout', 'horizontal');
        $this->assertSame('horizontal', $actor->fresh()->layout);
        $this->assertSame('vertical', $colleague->fresh()->layout);
        $this->assertFalse($actor->fresh()->is_super_admin);

        $this->actingAs($actor->fresh())->get(route('client.dashboard'))
            ->assertOk()->assertSee('data-layout="horizontal"', false);
        $this->actingAs($colleague->fresh())->get(route('client.dashboard'))
            ->assertOk()->assertSee('data-layout="vertical"', false);
    }

    public function test_platform_administrator_can_switch_back_to_vertical_layout(): void
    {
        $actor = $this->rootUser();
        $this->actingAs($actor)->patch(route('preferences.update'), ['layout' => 'horizontal'])
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($actor->fresh())->get(route('admin.dashboard'))
            ->assertOk()->assertSee('data-layout="horizontal"', false);

        $this->patchJson(route('preferences.update'), ['layout' => 'vertical'])->assertOk();
        $this->actingAs($actor->fresh())->get(route('admin.dashboard'))
            ->assertOk()->assertSee('data-layout="vertical"', false);
    }

    public function test_invalid_layout_cannot_overwrite_saved_preferences(): void
    {
        $actor = $this->user($this->tenant());
        $this->actingAs($actor)->patchJson(route('preferences.update'), ['layout' => 'horizontal'])->assertOk();
        foreach (['../admin', '', ['vertical']] as $invalid) {
            $this->patchJson(route('preferences.update'), ['layout' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('layout');
        }
        $this->assertSame('horizontal', $actor->fresh()->layout);
    }

    public function test_theme_and_language_updates_keep_the_selected_layout(): void
    {
        $actor = $this->user($this->tenant());
        $this->actingAs($actor)->patchJson(route('preferences.update'), ['layout' => 'horizontal'])->assertOk();
        $this->patchJson(route('preferences.update'), ['theme' => 'dark'])->assertOk();
        $this->patchJson(route('preferences.update'), ['locale' => 'ar'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $actor->id, 'layout' => 'horizontal', 'theme' => 'dark', 'locale' => 'ar']);
    }

    public function test_profile_updates_only_allowed_personal_fields(): void
    {
        $tenant = $this->tenant();
        $role = $this->role($tenant, ['users.view']);
        $user = $this->user($tenant, $role);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee($user->email);
        $this->patch(route('profile.update'), [
            'name' => 'Updated person', 'email' => 'new-profile@example.test', 'phone' => '01001234567',
            'job_title' => 'Operations', 'bio' => 'Profile description', 'tenant_id' => null,
            'is_super_admin' => true, 'role_id' => null, 'status' => 'inactive',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'name' => 'Updated person', 'email' => 'new-profile@example.test',
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'is_super_admin' => false, 'status' => 'active',
        ]);
    }

    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $user = $this->user($this->tenant());
        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'wrong', 'password' => 'NewPassword!456', 'password_confirmation' => 'NewPassword!456',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('SafePassword!123', $user->fresh()->password));
        $this->put(route('profile.password'), [
            'current_password' => 'SafePassword!123', 'password' => 'NewPassword!456', 'password_confirmation' => 'NewPassword!456',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassword!456', $user->fresh()->password));
    }

    public function test_all_five_locales_and_both_themes_are_persisted(): void
    {
        $user = $this->user($this->tenant());
        $this->actingAs($user);
        foreach (['ar', 'en', 'fr', 'de', 'es'] as $locale) {
            $this->patchJson(route('preferences.update'), ['locale' => $locale, 'theme' => 'dark'])->assertSuccessful();
            $this->assertSame($locale, $user->fresh()->locale);
            $this->assertSame('dark', $user->fresh()->theme);
            $this->get(route('client.dashboard'))->assertOk()->assertSee('lang="'.$locale.'"', false);
        }
        $this->patchJson(route('preferences.update'), ['locale' => 'en', 'theme' => 'light'])->assertSuccessful();
        $this->assertSame('light', $user->fresh()->theme);
    }

    public function test_unsupported_preferences_are_rejected(): void
    {
        $user = $this->user($this->tenant());
        $this->actingAs($user)->patchJson(route('preferences.update'), ['locale' => '../../bad', 'theme' => 'neon'])->assertStatus(422);
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('light', $user->fresh()->theme);
    }

    public function test_password_change_revokes_previously_issued_reset_links(): void
    {
        $user = $this->user($this->tenant());
        $token = Password::createToken($user);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'SafePassword!123', 'password' => 'ChangedPassword!456', 'password_confirmation' => 'ChangedPassword!456',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertFalse(Password::tokenExists($user, $token));
    }
}
