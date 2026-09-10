<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_error_page_language_form_never_echoes_password_post_data(): void
    {
        Route::post('/__test/expired-form', fn () => abort(419));
        $this->post('/__test/expired-form', [
            'password' => 'PrivatePassword!123',
            'password_confirmation' => 'PrivatePassword!123',
            'current_password' => 'PreviousPassword!123',
            '_token' => 'private-csrf-test-token',
        ])->assertStatus(419)
            ->assertDontSee('PrivatePassword!123')
            ->assertDontSee('PreviousPassword!123')
            ->assertDontSee('private-csrf-test-token');
    }

    public function test_guest_language_switch_preserves_only_reset_email_query(): void
    {
        $this->get(route('password.reset', ['token' => 'reset-token', 'email' => 'owner@example.test', 'unexpected' => 'private-query-value']))
            ->assertOk()
            ->assertSee('name="email" value="owner@example.test"', false)
            ->assertDontSee('private-query-value');
    }

    public function test_guests_are_redirected_from_protected_pages(): void
    {
        foreach (['dashboard', 'users.index', 'roles.index', 'profile.edit', 'notifications.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->get(route('login'))->assertOk();
    }

    public function test_active_user_can_login_and_logout(): void
    {
        $user = $this->user($this->tenant());
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'SafePassword!123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_wrong_password_and_inactive_account_cannot_login(): void
    {
        $user = $this->user($this->tenant());
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors();
        $this->assertGuest();
        $user->update(['status' => 'inactive']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'SafePassword!123'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_suspended_tenant_cannot_login_or_keep_using_an_existing_session(): void
    {
        $tenant = $this->tenant(['status' => 'suspended']);
        $user = $this->user($tenant);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'SafePassword!123'])->assertSessionHasErrors();
        $this->assertGuest();
        $response = $this->actingAs($user)->get(route('client.dashboard'));
        $this->assertContains($response->status(), [302, 403]);
        $this->assertGuest();
    }

    public function test_login_attempts_are_throttled(): void
    {
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'SafePassword!123'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'SafePassword!123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->travelBack();
    }

    public function test_password_reset_notification_and_token_work(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ChangedPassword!456',
            'password_confirmation' => 'ChangedPassword!456',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ChangedPassword!456', $user->fresh()->password));
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword!789',
            'password_confirmation' => 'AnotherPassword!789',
        ])->assertSessionHasErrors();
    }

    public function test_invalid_reset_token_never_changes_password(): void
    {
        $user = $this->user();
        $this->post(route('password.update'), [
            'token' => 'not-a-real-token', 'email' => $user->email,
            'password' => 'ChangedPassword!456', 'password_confirmation' => 'ChangedPassword!456',
        ])->assertSessionHasErrors();
        $this->assertTrue(Hash::check('SafePassword!123', $user->fresh()->password));
    }

    public function test_dashboard_routes_enforce_platform_and_tenant_boundaries(): void
    {
        $root = $this->rootUser();
        $this->actingAs($root)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('client.dashboard'))->assertForbidden();

        $tenantUser = $this->user($this->tenant());
        $this->actingAs($tenantUser)->get(route('dashboard'))->assertRedirect(route('client.dashboard'));
        $this->get(route('client.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.clients.index'))->assertForbidden();
    }
}
