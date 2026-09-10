<?php

namespace App\Providers;

use App\Services\PlatformMailConfigurator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PlatformMailConfigurator::class);
        $this->app->beforeResolving('mail.manager', function (): void {
            $this->app->make(PlatformMailConfigurator::class)->apply();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false)
        );

        foreach (['profile-updates' => 6, 'account-updates' => 15, 'logo-updates' => 15] as $name => $limit) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($limit)
                ->by($name.'|'.($request->user()?->id ?? $request->ip())));
        }
    }
}
