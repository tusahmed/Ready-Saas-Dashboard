<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function redirectFor(Request $request, string $permission, string $route, mixed $parameters = []): RedirectResponse
    {
        // Re-read permissions because editing an assigned role may revoke list access.
        return $request->user()->fresh()->canDo($permission)
            ? redirect()->route($route, $parameters)
            : redirect()->route('dashboard');
    }
}
