<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SystemNotification;
use App\Rules\SafePassword;
use App\Services\Access;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'role' => ['nullable', 'integer']]);
        $users = Access::users($request->user())->with('role')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('role'), fn ($q) => $q->where('role_id', $request->role))
            ->latest()->paginate(12)->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Access::roles($request->user())->orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        return view('users.form', ['user' => new User(['status' => 'active']), 'roles' => $this->roles($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Access::assignableRole($request->user(), $data['role_id'] ?? null);
        DB::transaction(function () use ($request, $data, $role) {
            $user = new User($data);
            $user->tenant_id = $request->user()->tenant_id;
            $user->role_id = $role?->id;
            $user->locale = $request->user()->locale;
            $user->save();
            $user->notify(new SystemNotification(['title_key' => 'app.welcome', 'body_key' => 'app.account_ready', 'url' => route('dashboard', absolute: false)]));
            ActivityLogger::record($request->user(), 'users.created', $user->name, route('users.index', absolute: false));
        });

        return $this->redirectFor($request, 'users.view', 'users.index')->with('success', __('app.saved'));
    }

    public function edit(Request $request, string $user)
    {
        $user = Access::users($request->user())->findOrFail($user);
        Access::editableUser($request->user(), $user);

        return view('users.form', ['user' => $user, 'roles' => $this->roles($request, $user)]);
    }

    public function update(Request $request, string $user)
    {
        $user = Access::users($request->user())->findOrFail($user);
        Access::editableUser($request->user(), $user);
        $data = $this->validated($request, $user);
        $roleId = isset($data['role_id']) ? (int) $data['role_id'] : null;
        if ($user->is_super_admin || $user->isOwner() || $user->id === $request->user()->id) {
            if ($roleId !== $user->role_id || $data['status'] !== $user->status) {
                throw ValidationException::withMessages(['role_id' => __('app.protected_account')]);
            }
        } else {
            Access::assignableRole($request->user(), $roleId);
        }
        DB::transaction(function () use ($request, $data, $user, $roleId) {
            $passwordChanged = ! empty($data['password']);
            if (! $passwordChanged) {
                unset($data['password']);
            }
            if ($data['email'] !== $user->email) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->email_verified_at = null;
            }
            $user->fill($data);
            $user->role_id = $roleId;
            if ($passwordChanged) {
                $user->remember_token = Str::random(60);
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            $user->save();
            if ($passwordChanged || $user->status !== 'active') {
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            }
            ActivityLogger::record($request->user(), 'users.updated', $user->name, route('users.index', absolute: false));
        });

        return $this->redirectFor($request, 'users.view', 'users.index')->with('success', __('app.saved'));
    }

    public function destroy(Request $request, string $user)
    {
        $user = Access::users($request->user())->findOrFail($user);
        Access::editableUser($request->user(), $user);
        abort_if($user->id === $request->user()->id || $user->is_super_admin || $user->isOwner(), 403, __('app.protected_account'));
        DB::transaction(function () use ($request, $user) {
            ActivityLogger::record($request->user(), 'users.deleted', $user->name);
            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        return back()->with('success', __('app.deleted'));
    }

    private function roles(Request $request, ?User $target = null)
    {
        return Access::roles($request->user())->orderBy('name')->get()->filter(function ($role) use ($request, $target) {
            if ($target?->role_id === $role->id) {
                return true;
            }

            return ! $role->is_owner && collect($role->permissions)->every(fn ($permission) => $request->user()->canDo($permission));
        });
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'], 'job_title' => ['nullable', 'string', 'max:120'], 'bio' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])], 'role_id' => ['nullable', 'integer'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', new SafePassword, Password::min(12)->mixedCase()->numbers()],
        ]);
    }
}
