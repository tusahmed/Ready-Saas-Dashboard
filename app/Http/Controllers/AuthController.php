<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\SafePassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', new SafePassword(allowPublicDemo: true)]]);
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('app.login_throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        $publicDemoPassword = app()->isProduction() && hash_equals(User::PUBLIC_DEMO_PASSWORD, $data['password']);
        if ($publicDemoPassword || ! Auth::attempt($data + ['status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('app.login_failed')]);
        }
        $user = $request->user();
        if ($user->tenant_id && $user->tenant?->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('app.account_disabled')]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgot()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink($request->only('email'));

        // The same response for unknown addresses prevents account enumeration.
        return back()->with('success', __('app.reset_link_sent'));
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate(['token' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'confirmed', new SafePassword, PasswordRule::min(12)->mixedCase()->numbers()]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('app.reset_failed')]);
        }

        return redirect()->route('login')->with('success', __('app.password_changed'));
    }
}
