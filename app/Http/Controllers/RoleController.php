<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\Access;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        return view('roles.index', ['roles' => Access::roles($request->user())->withCount('users')->orderByDesc('is_owner')->orderBy('name')->paginate(12), 'permissions' => Access::permissions($request->user())]);
    }

    public function create(Request $request)
    {
        return view('roles.form', ['role' => new Role(['permissions' => []]), 'permissions' => $this->grantable($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $data) {
            $role = new Role($data);
            $role->tenant_id = $request->user()->tenant_id;
            $role->save();
            ActivityLogger::record($request->user(), 'roles.created', $role->name, route('roles.index', absolute: false));
        });

        return $this->redirectFor($request, 'roles.view', 'roles.index')->with('success', __('app.saved'));
    }

    public function edit(Request $request, string $role)
    {
        $role = $this->editable($request, $role);

        return view('roles.form', ['role' => $role, 'permissions' => $this->grantable($request)]);
    }

    public function update(Request $request, string $role)
    {
        $role = $this->editable($request, $role);
        $data = $this->validated($request, $role);
        DB::transaction(function () use ($request, $data, $role) {
            $role->update($data);
            ActivityLogger::record($request->user(), 'roles.updated', $role->name, route('roles.index', absolute: false));
        });

        return $this->redirectFor($request, 'roles.view', 'roles.index')->with('success', __('app.saved'));
    }

    public function destroy(Request $request, string $role)
    {
        $role = $this->editable($request, $role);
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('app.role_has_users')]);
        }
        DB::transaction(function () use ($request, $role) {
            ActivityLogger::record($request->user(), 'roles.deleted', $role->name);
            $role->delete();
        });

        return back()->with('success', __('app.deleted'));
    }

    public function users(Request $request, string $role)
    {
        $role = Access::roles($request->user())->findOrFail($role);

        return response()->json(['users' => $role->users()->where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get(['name', 'email', 'status'])]);
    }

    private function editable(Request $request, string $id): Role
    {
        $role = Access::roles($request->user())->findOrFail($id);
        abort_if($role->is_owner, 403, __('app.protected_role'));
        foreach ($role->permissions ?? [] as $permission) {
            abort_unless($request->user()->canDo($permission), 403);
        }

        return $role;
    }

    private function grantable(Request $request): array
    {
        return array_filter(Access::permissions($request->user()), fn ($key) => $request->user()->canDo($key), ARRAY_FILTER_USE_KEY);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->where('tenant_id', $request->user()->tenant_id)->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:1000'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['string', 'distinct'],
        ]);
        $data['permissions'] = $data['permissions'] ?? [];
        Access::validatePermissions($request->user(), $data['permissions']);

        return $data;
    }
}
