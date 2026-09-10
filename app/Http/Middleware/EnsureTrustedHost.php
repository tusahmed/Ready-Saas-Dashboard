<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTrustedHost
{
    public function handle(Request $request, Closure $next)
    {
        // This starter uses path-based workspaces, not arbitrary tenant domains.
        // Do not let client-supplied Host headers affect links or redirects.
        if (app()->environment('production')) {
            $host = parse_url(config('app.url'), PHP_URL_HOST);
            abort_unless(is_string($host) && strcasecmp($request->getHost(), $host) === 0, 400);
        }

        return $next($request);
    }
}
