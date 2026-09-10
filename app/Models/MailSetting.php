<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'enabled', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name',
    ];

    protected $hidden = ['password'];

    protected $attributes = [
        'id' => 1,
        'enabled' => false,
        'port' => 587,
        'encryption' => 'tls',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public function getHasPasswordAttribute(): bool
    {
        return filled($this->getAttributes()['password'] ?? null);
    }
}
