<?php

namespace App\Support;

/**
 * Brazilian federative units (UF), used to validate the OAB section.
 */
final class BrazilianState
{
    public const CODES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public static function isValid(?string $uf): bool
    {
        return in_array(mb_strtoupper(trim((string) $uf)), self::CODES, true);
    }
}
