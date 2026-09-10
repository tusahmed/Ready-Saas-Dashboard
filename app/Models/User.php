<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    public const PUBLIC_DEMO_PASSWORD = 'OrbitDemo!2026';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'phone', 'job_title', 'bio', 'locale', 'theme', 'layout', 'status'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isPlatform(): bool
    {
        return $this->tenant_id === null;
    }

    public function usesPublicDemoPassword(): bool
    {
        if (! app()->isProduction()) {
            return false;
        }

        // Cache by password hash, so changing a password immediately changes the key.
        return Cache::remember('security.public-demo-password.'.hash('sha256', $this->getAuthPassword()), 3600,
            fn () => Hash::check(self::PUBLIC_DEMO_PASSWORD, $this->getAuthPassword()));
    }

    public function isOwner(): bool
    {
        return $this->role && $this->role->tenant_id === $this->tenant_id && $this->role->is_owner;
    }

    public function canDo(string $permission): bool
    {
        if ($this->status !== 'active' || ($this->tenant_id && $this->tenant?->status !== 'active')) {
            return false;
        }
        if ($this->is_super_admin && $this->isPlatform()) {
            return true;
        }
        if (! $this->role || $this->role->tenant_id !== $this->tenant_id) {
            return false;
        }
        if ($this->tenant_id && ! array_key_exists($permission, config('saas.tenant_permissions'))) {
            return false;
        }

        return ($this->isOwner() && $this->tenant_id !== null) || in_array($permission, $this->role->permissions ?? [], true);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim($this->name)))->take(2)->map(fn ($name) => mb_strtoupper(mb_substr($name, 0, 1)))->implode('');
    }
}
