<?php

namespace App\Support;

/**
 * Reads the RSA keys used to sign (private) and verify (public) the JWT.
 *
 * Keys come from environment variables as single-line values, so they fit in
 * .env files and Docker env_file:
 *   - "base64:<base64 of the PEM>"  (written by `php artisan jwt:keys`)
 *   - a raw PEM with "\n" escapes
 */
final class JwtKey
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

    public static function encode(string $pem): string
    {
        return 'base64:'.base64_encode($pem);
    }
}
