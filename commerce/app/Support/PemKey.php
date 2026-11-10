<?php

namespace App\Support;

/**
 * Reads a PEM key from a single-line environment value: "base64:<PEM>" (the
 * format written by Identity's `php artisan jwt:keys`) or a PEM with "\n" escapes.
 */
final class PemKey
{
    public static function fromEnv(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), true);

            return $decoded === false ? null : $decoded;
        }

        return str_replace('\n', "\n", $value);
    }
}
