<?php

use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\EnsureTrustedHost;
use App\Http\Middleware\PlatformOnly;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPreferences;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['smtp_password']);
        $middleware->prepend(SecurityHeaders::class);
        $middleware->append(EnsureTrustedHost::class);
        $middleware->web(append: [SetPreferences::class]);
        $middleware->alias([
            'active' => ActiveAccount::class,
            'permission' => RequirePermission::class,
            'platform' => PlatformOnly::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['owner_password', 'owner_password_confirmation', 'smtp_password']);
    })->create();
