<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class Access
{
    public static function users(User $actor): Builder
    {
        return User::query()->where('tenant_id', $actor->tenant_id);
    }

    public static function roles(User $actor): Builder
    {
        return Role::query()->where('tenant_id', $actor->tenant_id);
    }

    public static function permissions(User $actor): array
    {
        return config($actor->isPlatform() ? 'saas.permissions' : 'saas.tenant_permissions');
    }

    public static function editableUser(User $actor, User $target): void
    {
        abort_unless($actor->tenant_id === $target->tenant_id, 404);
        if ($actor->id === $target->id || ($actor->is_super_admin && $actor->isPlatform())) {
            return;
        }
        abort_if($target->is_super_admin || $target->isOwner(), 403);
        foreach ($target->role?->permissions ?? [] as $permission) {
            abort_unless($actor->canDo($permission), 403);
        }
    }

    public static function assignableRole(User $actor, ?int $id): ?Role
    {
        if (! $id) {
            return null;
        }
        $role = self::roles($actor)->find($id);
        if (! $role || $role->is_owner) {
            throw ValidationException::withMessages(['role_id' => __('app.invalid_role')]);
        }
        foreach ($role->permissions ?? [] as $permission) {
            if (! $actor->canDo($permission)) {
                throw ValidationException::withMessages(['role_id' => __('app.permission_escalation')]);
            }
        }

        return $role;
    }

    public static function validatePermissions(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (! array_key_exists($permission, self::permissions($actor)) || ! $actor->canDo($permission)) {
                throw ValidationException::withMessages(['permissions' => __('app.permission_escalation')]);
            }
        }
    }
}
