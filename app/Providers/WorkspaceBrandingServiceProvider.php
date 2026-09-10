<?php

namespace App\Providers;

use App\Models\WorkspaceSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WorkspaceBrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('workspaceBranding', WorkspaceSetting::forUser(auth()->user()));
        });
    }
}
