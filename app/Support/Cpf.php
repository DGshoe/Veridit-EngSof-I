<?php

namespace App\Support;

/**
 * Brazilian CPF helpers: normalization and check-digit validation.
 */
final class Cpf
{
    /** Keeps only the digits ("529.982.247-25" -> "52998224725"). */
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    /** Accepts masked or unmasked input. */
    public static function isValid(?string $value): bool
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false; // wrong length or repeated sequence (111.111.111-11)
        }

        return substr($cpf, 9, 2) === self::checkDigits(substr($cpf, 0, 9));
    }

    /** Computes the two check digits for a 9-digit base. */
    public static function checkDigits(string $base): string
    {
        $digits = array_map('intval', str_split($base));

        foreach ([10, 11] as $weight) {
            $sum = 0;
            foreach ($digits as $i => $digit) {
                $sum += $digit * ($weight - $i);
            }
            $remainder = ($sum * 10) % 11;
            $digits[] = $remainder === 10 ? 0 : $remainder;
        }

        return $digits[9].$digits[10];
    }
}
