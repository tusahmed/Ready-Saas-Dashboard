<?php

namespace App\Support;

final class InternalUrl
{
    public static function safe(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 255) {
            return null;
        }

        $decoded = $value;
        for ($pass = 0; $pass < 4; $pass++) {
            if (! str_starts_with($decoded, '/') || str_starts_with($decoded, '//')
                || preg_match('/[\\\\\x00-\x20\x7f]/', $decoded)) {
                return null;
            }
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                return $value;
            }
            $decoded = $next;
        }

        return null;
    }
}
