<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkspaceSetting extends Model
{
    protected $fillable = [
        'business_name', 'business_email', 'phone', 'website', 'address', 'country',
        'tax_number', 'registration_number', 'description',
    ];

    protected static function booted(): void
    {
        static::deleted(function (WorkspaceSetting $settings): void {
            $path = $settings->logo_path;
            if ($path) {
                // A failed tenant/settings transaction must retain its current logo.
                DB::afterCommit(fn () => $settings->deleteLogoFile($path));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function scopeFor(User $user): string
    {
        return $user->isPlatform() ? 'platform' : 'tenant-'.$user->tenant_id;
    }

    public static function forUser(User $user): static
    {
        $scope = static::scopeFor($user);
        $settings = static::query()->where('scope', $scope)->first();
        if ($settings) {
            return $settings;
        }

        $tenant = $user->isPlatform() ? null : $user->tenant;

        // Reading the settings page or rendering a brand never creates database rows.
        return (new static)->forceFill([
            'scope' => $scope,
            'tenant_id' => $user->tenant_id,
            'business_name' => $tenant?->name ?? 'Orbit',
            'business_email' => $tenant?->email,
            'phone' => $tenant?->phone,
            'website' => $tenant?->website,
            'description' => $tenant?->description,
        ]);
    }

    public function ownsLogoPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('/\Aworkspace-logos\/'.preg_quote($this->scope, '/').'\/[a-zA-Z0-9]{40}\.(?:png|jpg|webp)\z/', $path) === 1;
    }

    public function deleteLogoFile(?string $path = null): void
    {
        $path ??= $this->logo_path;
        if ($this->ownsLogoPath($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->ownsLogoPath($this->logo_path) || ! Storage::disk('local')->exists($this->logo_path)) {
            return null;
        }

        return route('settings.logo', ['v' => substr(hash('sha256', $this->logo_path), 0, 12)]);
    }
}
