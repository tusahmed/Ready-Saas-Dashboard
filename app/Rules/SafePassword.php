<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt accepts at most 72 bytes and rejects NUL. Validate before hashing.
        if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('app.password_unsupported')->translate();
        }
    }
}
