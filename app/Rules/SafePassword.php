<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePassword implements ValidationRule
{
    public function __construct(private readonly bool $allowPublicDemo = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt accepts at most 72 bytes and rejects NUL. Validate before hashing.
        if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('app.password_unsupported')->translate();
        } elseif (! $this->allowPublicDemo && app()->isProduction() && hash_equals(User::PUBLIC_DEMO_PASSWORD, $value)) {
            $fail('app.password_public_demo')->translate();
        }
    }
}
