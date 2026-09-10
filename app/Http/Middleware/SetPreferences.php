<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetPreferences
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() && in_array($request->query('locale'), array_keys(config('saas.locales')), true)) {
            $request->session()->put('locale', $request->query('locale'));
        }
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));
        App::setLocale(array_key_exists($locale, config('saas.locales')) ? $locale : 'ar');

        return $next($request);
    }
}
