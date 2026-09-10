<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'auth.session', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/app', [DashboardController::class, 'client'])->name('client.dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:profile-updates')->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
    Route::patch('/preferences', [ProfileController::class, 'preferences'])->name('preferences.update');
    Route::get('/settings', fn () => redirect()->route('settings.general'))->middleware('permission:settings.manage')->name('settings');
    Route::get('/settings/general', [SettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.general');
    Route::patch('/settings/general', [SettingsController::class, 'update'])->middleware(['permission:settings.manage', 'throttle:logo-updates'])->name('settings.general.update');
    Route::get('/settings/logo', [SettingsController::class, 'logo'])->name('settings.logo');
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');
    foreach (['users' => UserController::class, 'roles' => RoleController::class] as $name => $controller) {
        $parameter = rtrim($name, 's');
        Route::get("/$name", [$controller, 'index'])->middleware("permission:$name.view")->name("$name.index");
        Route::get("/$name/create", [$controller, 'create'])->middleware("permission:$name.create")->name("$name.create");
        Route::post("/$name", [$controller, 'store'])->middleware("permission:$name.create")->name("$name.store");
        Route::get("/$name/{{$parameter}}/edit", [$controller, 'edit'])->middleware("permission:$name.update")->name("$name.edit");
        Route::match(['put', 'patch'], "/$name/{{$parameter}}", [$controller, 'update'])->middleware(["permission:$name.update", 'throttle:account-updates'])->name("$name.update");
        Route::delete("/$name/{{$parameter}}", [$controller, 'destroy'])->middleware("permission:$name.delete")->name("$name.destroy");
    }
    Route::get('/roles/{role}/users', [RoleController::class, 'users'])->middleware('permission:roles.view')->name('roles.users');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::prefix('admin')->name('admin.')->middleware('platform')->group(function () {
        Route::get('/settings/smtp', [MailSettingsController::class, 'edit'])->name('settings.smtp');
        Route::patch('/settings/smtp', [MailSettingsController::class, 'update'])->middleware('throttle:15,1')->name('settings.smtp.update');
        Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:clients.view')->name('clients.index');
        Route::get('/clients/create', [ClientController::class, 'create'])->middleware('permission:clients.create')->name('clients.create');
        Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:clients.create')->name('clients.store');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->middleware('permission:clients.view')->name('clients.show');
        Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->middleware('permission:clients.update')->name('clients.edit');
        Route::match(['put', 'patch'], '/clients/{client}', [ClientController::class, 'update'])->middleware('permission:clients.update')->name('clients.update');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete')->name('clients.destroy');
        Route::get('/notifications/create', [NotificationController::class, 'create'])->middleware('permission:notifications.send')->name('notifications.create');
        Route::post('/notifications', [NotificationController::class, 'store'])->middleware(['permission:notifications.send', 'throttle:5,1'])->name('notifications.store');
    });
});
