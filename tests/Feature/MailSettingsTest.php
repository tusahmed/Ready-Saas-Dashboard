<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Services\PlatformMailConfigurator;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    private function settingsPayload(array $overrides = []): array
    {
        return array_replace([
            'enabled' => true,
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user@example.test',
            'smtp_password' => 'SmtpOnlySecret!456',
            'from_address' => 'hello@example.test',
            'from_name' => 'Orbit Workspace',
        ], $overrides);
    }

    private function saveSettings(array $overrides = []): void
    {
        $this->patch(route('admin.settings.smtp.update'), $this->settingsPayload($overrides))
            ->assertRedirect(route('admin.settings.smtp'))->assertSessionHasNoErrors();
    }

    public function test_only_a_real_platform_super_administrator_can_view_or_change_smtp(): void
    {
        $powerfulStaff = $this->user(role: $this->role(permissions: array_keys(config('saas.permissions'))));
        $tenantOwner = $this->owner($this->tenant());
        $invalidTenantRoot = $this->user($this->tenant(), attributes: ['is_super_admin' => true]);

        foreach ([$powerfulStaff, $tenantOwner, $invalidTenantRoot] as $actor) {
            $this->actingAs($actor)->get(route('admin.settings.smtp'))->assertForbidden();
            $this->patch(route('admin.settings.smtp.update'), $this->settingsPayload())->assertForbidden();
        }
        $this->assertDatabaseCount('mail_settings', 0);
        $this->actingAs($this->rootUser())->get(route('admin.settings.smtp'))->assertOk();
    }

    public function test_password_is_encrypted_at_rest_and_never_rendered_or_serialized(): void
    {
        $this->actingAs($this->rootUser());
        $secret = '  Smtp credential with spaces!456  ';
        $this->saveSettings(['smtp_password' => $secret, 'id' => 99]);

        $settings = MailSetting::findOrFail(1);
        $ciphertext = DB::table('mail_settings')->where('id', 1)->value('password');
        $this->assertNotSame($secret, $ciphertext);
        $this->assertSame($secret, Crypt::decryptString($ciphertext));
        $this->assertSame($secret, $settings->password);
        $this->assertTrue($settings->has_password);
        $this->assertArrayNotHasKey('password', $settings->toArray());
        $this->assertDatabaseCount('mail_settings', 1);
        $this->get(route('admin.settings.smtp'))->assertOk()
            ->assertSee('name="smtp_password"', false)
            ->assertDontSee($secret, false)
            ->assertDontSee($ciphertext, false);
    }

    public function test_blank_password_preserves_stored_secret_and_explicit_clear_removes_it(): void
    {
        $this->actingAs($this->rootUser());
        $this->saveSettings();
        $ciphertext = DB::table('mail_settings')->where('id', 1)->value('password');
        $this->saveSettings(['smtp_password' => '', 'host' => 'updated.example.test']);
        $this->assertSame($ciphertext, DB::table('mail_settings')->where('id', 1)->value('password'));
        $this->assertSame('updated.example.test', MailSetting::findOrFail(1)->host);

        $this->saveSettings(['smtp_password' => '', 'clear_password' => true]);
        $this->assertNull(MailSetting::findOrFail(1)->password);
        $this->assertFalse(MailSetting::findOrFail(1)->has_password);
    }

    public function test_validation_never_flashes_smtp_password_and_rejects_ambiguous_clear(): void
    {
        $this->actingAs($this->rootUser());
        $secret = 'MustNeverEnterOldInput!789';
        $this->from(route('admin.settings.smtp'))->patch(route('admin.settings.smtp.update'), $this->settingsPayload([
            'port' => 70000, 'smtp_password' => $secret,
        ]))->assertSessionHasErrors('port')->assertSessionMissing('_old_input.smtp_password');
        $this->assertStringNotContainsString($secret, json_encode(session()->all()));
        $this->get(route('admin.settings.smtp'))->assertOk()->assertDontSee($secret, false);

        $this->patch(route('admin.settings.smtp.update'), $this->settingsPayload(['clear_password' => true]))
            ->assertSessionHasErrors('smtp_password')->assertSessionMissing('_old_input.smtp_password');
        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_smtp_requires_valid_configuration_when_enabled(): void
    {
        $this->actingAs($this->rootUser());
        $this->patchJson(route('admin.settings.smtp.update'), ['enabled' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['host', 'port', 'encryption', 'from_address', 'from_name']);
        foreach ([['host' => 'https://smtp.example.test/path'], ['encryption' => 'starttls-optional'], ['from_address' => 'invalid-address'], ['from_name' => "Injected\r\nHeader"], ['enabled' => 'invalid']] as $invalid) {
            $this->patchJson(route('admin.settings.smtp.update'), $this->settingsPayload($invalid))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_saved_configuration_controls_transport_and_purges_old_mailer_instances(): void
    {
        $this->actingAs($this->rootUser());
        $this->saveSettings();
        $manager = app('mail.manager');
        $firstMailer = $manager->mailer();
        $firstTransport = $firstMailer->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $firstTransport);
        $this->assertSame('platform_smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', $firstTransport->getStream()->getHost());
        $this->assertSame(587, $firstTransport->getStream()->getPort());
        $this->assertTrue($firstTransport->isAutoTls());
        $this->assertTrue($firstTransport->isTlsRequired());
        $this->assertFalse($firstTransport->getStream()->isTls());

        $this->saveSettings(['host' => 'new-smtp.example.test', 'smtp_password' => 'NewSmtpSecret!789']);
        $secondMailer = $manager->mailer();
        $this->assertNotSame($firstMailer, $secondMailer);
        $this->assertSame('new-smtp.example.test', $secondMailer->getSymfonyTransport()->getStream()->getHost());
        $this->assertSame('NewSmtpSecret!789', config('mail.mailers.platform_smtp.password'));
    }

    public function test_ssl_and_unencrypted_modes_use_the_installed_symfony_options(): void
    {
        $this->actingAs($this->rootUser());
        foreach ([['encryption' => 'ssl', 'port' => 465], ['encryption' => 'none', 'port' => 465]] as $mode) {
            $this->saveSettings($mode);
            $transport = app('mail.manager')->mailer()->getSymfonyTransport();
            $this->assertInstanceOf(EsmtpTransport::class, $transport);
            $this->assertFalse($transport->isAutoTls());
            $this->assertSame($mode['encryption'] === 'ssl', $transport->getStream()->isTls());
            $this->assertSame($mode['encryption'] === 'ssl', $transport->isTlsRequired());
        }
    }

    public function test_disabling_saved_smtp_restores_original_mailer_and_from_defaults(): void
    {
        config(['mail.default' => 'array', 'mail.from' => ['address' => 'fallback@example.test', 'name' => 'Fallback']]);
        $this->actingAs($this->rootUser());
        $this->saveSettings();
        $manager = app('mail.manager');
        $smtpMailer = $manager->mailer();

        $this->patch(route('admin.settings.smtp.update'), ['enabled' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('array', config('mail.default'));
        $this->assertSame(['address' => 'fallback@example.test', 'name' => 'Fallback'], config('mail.from'));
        $this->assertNull(config('mail.mailers.platform_smtp'));
        $this->assertNotSame($smtpMailer, $manager->mailer());
        $this->assertInstanceOf(ArrayTransport::class, $manager->mailer()->getSymfonyTransport());
        $this->assertSame('SmtpOnlySecret!456', MailSetting::findOrFail(1)->password);
    }

    public function test_password_reset_uses_saved_smtp_configuration_without_network_delivery(): void
    {
        MailSetting::create([
            'enabled' => true, 'host' => 'reset-smtp.example.test', 'port' => 587,
            'encryption' => 'tls', 'username' => 'reset-user', 'password' => 'ResetSmtpSecret!789',
            'from_address' => 'accounts@example.test', 'from_name' => 'Account Team',
        ]);
        $transport = new ArrayTransport;
        $capturedConfig = null;
        // Only this test's transport factory is replaced; the real reset broker,
        // notification channel and persisted mail configuration still run.
        app('mail.manager')->extend('smtp', function ($configuration) use ($transport, &$capturedConfig) {
            $capturedConfig = $configuration;

            return $transport;
        });

        $recipient = $this->user($this->tenant());
        $this->assertSame(Password::RESET_LINK_SENT, Password::sendResetLink(['email' => $recipient->email]));
        $this->assertSame('reset-smtp.example.test', $capturedConfig['host']);
        $this->assertSame('ResetSmtpSecret!789', $capturedConfig['password']);
        $this->assertCount(1, $transport->messages());
        $email = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame('accounts@example.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Account Team', $email->getFrom()[0]->getName());
        $this->assertSame($recipient->email, $email->getTo()[0]->getAddress());
    }

    public function test_missing_settings_table_keeps_environment_mail_configuration_usable(): void
    {
        config(['mail.default' => 'array']);
        Schema::shouldReceive('hasTable')->once()->with('mail_settings')->andReturn(false);
        app(PlatformMailConfigurator::class)->apply();
        $this->assertSame('array', config('mail.default'));
        $this->assertNull(config('mail.mailers.platform_smtp'));
    }
}
