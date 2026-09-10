<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Schema;

class PlatformMailConfigurator
{
    private array $fallback;

    private ?string $fingerprint = null;

    private bool $applying = false;

    public function __construct(private Application $app)
    {
        // Keep the environment/config-cache values separately, never write database
        // credentials into .env or the application's cached configuration file.
        $this->fallback = $app['config']->get('mail', []);
    }

    public function apply(): void
    {
        // Resolving an already-created manager invokes beforeResolving again.
        if ($this->applying) {
            return;
        }

        $this->applying = true;
        try {
            $mail = $this->fallback;
            $settings = $this->savedSettings();
            if ($settings?->enabled) {
                // A database copied from local development may still contain
                // "none". Live environments must also enforce TLS at delivery.
                $requireTls = ! $this->app->environment(['local', 'testing']) || $settings->encryption !== 'none';
                $mail['default'] = 'platform_smtp';
                $mail['mailers']['platform_smtp'] = [
                    'transport' => 'smtp',
                    // Laravel 12 passes these options to Symfony's SMTP factory.
                    // STARTTLS is mandatory for TLS; "none" also disables auto-TLS.
                    'scheme' => $settings->encryption === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $settings->host,
                    'port' => $settings->port,
                    'username' => $settings->username,
                    'password' => $settings->password,
                    'auto_tls' => $settings->encryption !== 'ssl' && $requireTls,
                    'require_tls' => $requireTls,
                    'timeout' => 15,
                    'local_domain' => $this->fallback['mailers']['smtp']['local_domain'] ?? null,
                ];
                $mail['from'] = ['address' => $settings->from_address, 'name' => $settings->from_name];
            }

            $fingerprint = hash('sha256', serialize($mail));
            if ($fingerprint === $this->fingerprint && $this->app['config']->get('mail') === $mail) {
                return;
            }

            $this->app['config']->set('mail', $mail);
            $this->fingerprint = $fingerprint;
            if ($this->app->resolved('mail.manager')) {
                $manager = $this->app->make('mail.manager');
                if ($manager instanceof MailManager) {
                    $manager->forgetMailers();
                }
            }
        } finally {
            $this->applying = false;
        }
    }

    private function savedSettings(): ?MailSetting
    {
        try {
            // Installer, migrations and CLI discovery must work before this table
            // exists, including when the database has not been created yet.
            return Schema::hasTable('mail_settings') ? MailSetting::query()->find(1) : null;
        } catch (QueryException) {
            return null;
        }
    }
}
