<?php

namespace App\Providers;

use App\Services\PlatformMailConfigurator;
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
        //
    }
}
