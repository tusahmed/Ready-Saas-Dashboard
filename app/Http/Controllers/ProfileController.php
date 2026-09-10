<?php

namespace App\Http\Controllers;

use App\Rules\SafePassword;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'], 'job_title' => ['nullable', 'string', 'max:120'], 'bio' => ['nullable', 'string', 'max:2000'],
        ]);
        $emailChanged = $data['email'] !== $user->email;
        if ($emailChanged) {
            $request->validate(['current_password' => ['required', 'string', new SafePassword, 'current_password']]);
        }
        DB::transaction(function () use ($data, $user, $emailChanged, $request) {
            if ($emailChanged) {
                DB::table('password_reset_tokens')->whereIn('email', [$user->email, $data['email']])->delete();
                $user->email_verified_at = null;
                $user->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            }
            $user->fill($data)->save();
            ActivityLogger::record($user, 'profile.updated', $user->name, route('profile.edit', absolute: false));
        });
        if ($emailChanged) {
            $request->session()->regenerate(true);
        }

        return back()->with('success', __('app.saved'));
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'string', new SafePassword, 'current_password'], 'password' => ['required', 'confirmed', new SafePassword, Password::min(12)->mixedCase()->numbers()]]);
        DB::transaction(function () use ($request, $data) {
            $request->user()->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            DB::table('password_reset_tokens')->where('email', $request->user()->email)->delete();
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
            ActivityLogger::record($request->user(), 'password.updated', __('app.password_changed'));
        });
        $request->session()->regenerate(true);

        return back()->with('success', __('app.password_changed'));
    }

    public function preferences(Request $request)
    {
        $data = $request->validate([
            'locale' => ['sometimes', 'required', Rule::in(array_keys(config('saas.locales')))],
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'layout' => ['sometimes', 'required', Rule::in(['vertical', 'horizontal'])],
        ]);
        $request->user()->fill($data)->save();
        foreach ($data as $key => $value) {
            $request->session()->put($key, $value);
        }
        if ($request->expectsJson()) {
            return response()->json(['saved' => true] + $data);
        }

        return back()->with('success', __('app.preferences_saved'));
    }
}
