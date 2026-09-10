<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'email', 'phone', 'website', 'description', 'plan', 'status', 'trial_ends_at'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'date'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }
}
