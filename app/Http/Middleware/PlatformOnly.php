<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PlatformOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isPlatform(), 403);

        return $next($request);
    }
}
