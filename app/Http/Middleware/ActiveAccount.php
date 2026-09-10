<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ($user->status !== 'active' || ($user->tenant_id && $user->tenant?->status !== 'active') || $user->usesPublicDemoPassword())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => __('app.account_disabled')], 403);
            }

            return redirect()->route('login')->withErrors(['email' => __('app.account_disabled')]);
        }

        return $next($request);
    }
}
