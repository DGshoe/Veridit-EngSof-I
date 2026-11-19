<?php

namespace App\Rules;

use App\Support\Cpf as CpfHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valid Brazilian CPF (masked or not): check digits, no repeated sequences.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CpfHelper::isValid($value)) {
            $fail('CPF inválido.');
        }
    }
}
