<?php

namespace Tests\Feature;

use App\Support\InternalUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class HttpSecurityTest extends TestCase
{
    public function test_security_headers_cover_pages_errors_and_csrf_failures(): void
    {
        $this->enforceCsrf();
        foreach ([
            $this->get('/login'),
            $this->get('/missing-security-test-page'),
            $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'private-value']),
        ] as $response) {
            $response->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
            $csp = $response->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("script-src 'self';", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString("frame-ancestors 'none'", $csp);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->get('/login')->assertDontSee('<script>', false)->assertSee('assets/theme-init.js', false);
    }

    public function test_protected_mutations_require_a_real_matching_csrf_token(): void
    {
        $this->enforceCsrf();
        $this->actingAs($this->rootUser())->withSession(['_token' => 'correct-token']);
        $this->patchJson('/preferences', ['theme' => 'dark'])->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', 'wrong-token')
            ->patchJson('/preferences', ['theme' => 'dark'])->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', 'correct-token')
            ->patchJson('/preferences', ['theme' => 'dark'])->assertOk();
    }

    public function test_hsts_is_only_sent_on_secure_requests(): void
    {
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_production_rejects_untrusted_hosts_and_untrusted_forwarded_hosts(): void
    {
        config(['app.url' => 'https://saas.example.test']);
        $this->app->instance('env', 'production');
        $this->get('https://attacker.example.test/login')->assertStatus(400);
        $this->get('https://sub.saas.example.test/login')->assertStatus(400);
        $response = $this->withHeader('X-Forwarded-Host', 'attacker.example.test')
            ->get('https://saas.example.test/login')->assertOk();
        $response->assertDontSee('attacker.example.test');
    }

    public function test_password_reset_links_always_use_the_configured_origin(): void
    {
        config(['app.url' => 'https://saas.example.test']);
        $user = $this->user();
        Route::get('/__security/reset-link', fn () => response()->json([
            'url' => (new ResetPassword('test-token'))->toMail($user)->actionUrl,
        ]));
        $this->get('http://attacker.example.test/__security/reset-link')->assertOk()
            ->assertJsonPath('url', 'https://saas.example.test/reset-password/test-token?email='.urlencode($user->email));
    }

    public function test_legacy_notification_content_is_escaped_and_unsafe_urls_are_removed(): void
    {
        $user = $this->user($this->tenant());
        $payload = '</script><img src=x onerror=alert(1)>';
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'legacy',
            'data' => ['title' => $payload, 'body' => $payload, 'url' => 'javascript:alert(1)'],
        ]);
        $this->actingAs($user)->get('/notifications')->assertOk()
            ->assertSee(e($payload), false)->assertDontSee($payload, false)
            ->assertDontSee('href="javascript:', false);
        $this->getJson('/notifications/feed')->assertOk()->assertJsonPath('notifications.0.url', null);
    }

    public function test_only_internal_notification_links_are_accepted(): void
    {
        foreach (['javascript:alert(1)', 'https://evil.test', '//evil.test', '/\\evil.test',
            '/%2fevil.test', '/%255cevil.test', "/path\nheader", ['bad']] as $url) {
            $this->assertNull(InternalUrl::safe($url));
        }
        $this->assertSame('/users?filter=active', InternalUrl::safe('/users?filter=active'));
    }

    public function test_no_generic_private_storage_routes_are_registered(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    private function enforceCsrf(): void
    {
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }
}
