<?php

namespace App\Support;

/**
 * Masks personal data for API responses and logs (ADR-012).
 */
final class Mask
{
    /** "52998224725" -> "***.982.247-**" */
    public static function cpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digits = Cpf::normalize($cpf);

        if (strlen($digits) !== 11) {
            return '***.***.***-**';
        }

        return sprintf('***.%s.%s-**', substr($digits, 3, 3), substr($digits, 6, 3));
    }

    /** "maria@exemplo.com" -> "m***@exemplo.com" */
    public static function email(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***'.($domain !== '' ? '@'.$domain : '');
    }
}
