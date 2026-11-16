<?php

namespace App\Rules;

use App\Support\BrazilianState;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * OAB registration parts: the number (1–8 digits) or the UF (valid Brazilian state).
 *
 *   'oab_number' => [new Oab(Oab::NUMBER)]
 *   'oab_uf'     => [new Oab(Oab::UF)]
 */
class Oab implements ValidationRule
{
    public const NUMBER = 'number';

    public const UF = 'uf';

    public function __construct(private readonly string $part) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_scalar($value) ? (string) $value : '';

        if ($this->part === self::NUMBER && ! preg_match('/^\d{1,8}$/', $value)) {
            $fail('O número da OAB deve conter apenas dígitos (de 1 a 8).');
        }

        if ($this->part === self::UF && ! BrazilianState::isValid($value)) {
            $fail('A UF da OAB deve ser uma unidade federativa brasileira válida.');
        }
    }
}
