<?php

namespace Tests\Feature;

use App\Http\Middleware\ActiveAccount;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    public function test_profile_email_change_requires_current_password_and_revokes_existing_credentials(): void
    {
        $user = $this->user($this->tenant(), attributes: ['remember_token' => str_repeat('a', 60)]);
        $oldEmail = $user->email;
        $token = Password::createToken($user);
        $this->sessionFor($user, 'other-profile-session');
        $payload = ['name' => $user->name, 'email' => 'changed@example.test'];

        $this->actingAs($user)->patchJson(route('profile.update'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson(route('profile.update'), $payload + ['current_password' => 'IncorrectPassword!123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($oldEmail, $user->fresh()->email);
        $this->assertTrue(Password::tokenExists($user, $token));

        $this->patch(route('profile.update'), $payload + ['current_password' => 'SafePassword!123'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($payload['email'], $user->fresh()->email);
        $this->assertNotSame(str_repeat('a', 60), $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-profile-session']);
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_profile_non_sensitive_changes_do_not_require_password(): void
    {
        $user = $this->user($this->tenant());
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Updated Name', 'email' => $user->email])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_user_management_cannot_bypass_current_password_for_own_credentials(): void
    {
        $owner = $this->owner($this->tenant());
        $payload = $this->userPayload($owner->role, ['name' => $owner->name, 'email' => $owner->email]);
        $this->actingAs($owner)->putJson(route('users.update', $owner), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $emailPayload = array_replace($payload, ['email' => 'takeover@example.test', 'password' => '', 'password_confirmation' => '']);
        $this->putJson(route('users.update', $owner), $emailPayload)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertNotSame($emailPayload['email'], $owner->fresh()->email);

        $payload = array_replace($payload, ['password' => 'PrivateChanged!456', 'password_confirmation' => 'PrivateChanged!456', 'current_password' => 'SafePassword!123']);
        $this->put(route('users.update', $owner), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('PrivateChanged!456', $owner->fresh()->password));
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_disabling_a_user_revokes_sessions_remember_cookie_and_reset_tokens(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $role = $this->role($tenant, ['users.view']);
        $user = $this->user($tenant, $role, ['remember_token' => str_repeat('b', 60)]);
        Password::createToken($user);
        $this->sessionFor($user, 'disabled-user-session');
        $payload = $this->userPayload($role, ['email' => $user->email, 'password' => '', 'password_confirmation' => '', 'status' => 'inactive']);

        $this->actingAs($owner)->put(route('users.update', $user), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame(str_repeat('b', 60), $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['id' => 'disabled-user-session']);
    }

    public function test_admin_email_change_revokes_the_targets_existing_sessions_and_tokens(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $role = $this->role($tenant);
        $user = $this->user($tenant, $role, ['remember_token' => str_repeat('c', 60)]);
        $oldEmail = $user->email;
        Password::createToken($user);
        $this->sessionFor($user, 'changed-user-session');
        $payload = $this->userPayload($role, ['email' => 'admin-changed@example.test', 'password' => '', 'password_confirmation' => '']);

        $this->actingAs($owner)->put(route('users.update', $user), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame(str_repeat('c', 60), $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertDatabaseMissing('sessions', ['id' => 'changed-user-session']);
    }

    public function test_suspending_a_tenant_revokes_credentials_only_for_its_users(): void
    {
        $tenant = $this->tenant();
        $target = $this->user($tenant, attributes: ['remember_token' => str_repeat('d', 60)]);
        $other = $this->user($this->tenant(), attributes: ['remember_token' => str_repeat('e', 60)]);
        Password::createToken($target);
        Password::createToken($other);
        $this->sessionFor($target, 'suspended-tenant-session');
        $this->sessionFor($other, 'unaffected-tenant-session');

        $this->actingAs($this->rootUser())->put(route('admin.clients.update', $tenant), [
            'name' => $tenant->name, 'slug' => $tenant->slug, 'email' => $tenant->email,
            'plan' => $tenant->plan, 'status' => 'suspended',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($target->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'suspended-tenant-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertSame(str_repeat('e', 60), $other->fresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'unaffected-tenant-session']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $other->email]);
    }

    public function test_malformed_password_reset_token_is_rejected_without_changing_password(): void
    {
        $user = $this->user();
        foreach ([['unexpected' => 'token'], str_repeat('x', 1000)] as $token) {
            $this->postJson(route('password.update'), [
                'email' => $user->email, 'token' => $token,
                'password' => 'ChangedPassword!123', 'password_confirmation' => 'ChangedPassword!123',
            ])->assertUnprocessable()->assertJsonValidationErrors('token');
        }
        $this->assertTrue(Hash::check('SafePassword!123', $user->fresh()->password));
    }

    public function test_password_changed_elsewhere_invalidates_a_preexisting_session_hash(): void
    {
        $user = $this->user($this->tenant());
        $oldHash = $user->getAuthPassword();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $user->update(['password' => 'ExternallyChanged!456']);

        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])
            ->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_public_demo_password_is_rejected_for_any_production_login(): void
    {
        $user = $this->user(attributes: ['password' => User::PUBLIC_DEMO_PASSWORD]);
        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->postJson(route('login.store'), ['email' => $user->email, 'password' => User::PUBLIC_DEMO_PASSWORD])
            ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonPath('errors.email.0', __('app.login_failed'));
        $this->assertGuest();
    }

    public function test_existing_demo_session_is_blocked_in_production_and_password_change_clears_guard(): void
    {
        $user = $this->user(attributes: ['password' => User::PUBLIC_DEMO_PASSWORD]);
        $this->app['env'] = 'production';
        $this->actingAs($user);
        $request = Request::create('/profile', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);
        $response = (new ActiveAccount)->handle($request, fn () => response('must not run'));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertGuest();
        $this->assertTrue($user->usesPublicDemoPassword());

        $user->update(['password' => 'PrivateChanged!456']);
        $this->assertFalse($user->fresh()->usesPublicDemoPassword());
    }

    private function sessionFor(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id,
            'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        ]);
    }
}
