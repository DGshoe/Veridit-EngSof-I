<?php

namespace App\Http\Requests;

use App\Enums\UserType;
use App\Rules\Cpf;
use App\Rules\Oab;
use App\Support\Cpf as CpfHelper;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

/** REQ01 — public registration of "advogado" or "comum". */
#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'password', 'password_confirmation', 'cpf', 'type'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Maria Souza'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 150, example: 'maria@exemplo.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'Mínimo 8 caracteres, com letras e números', example: 'Senha1234'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Senha1234'),
        new OA\Property(property: 'cpf', type: 'string', description: 'Com ou sem máscara; dígitos verificadores validados', example: '529.982.247-25'),
        new OA\Property(property: 'type', type: 'string', enum: ['advogado', 'comum'], description: '"empresa" ainda não disponível (422)', example: 'advogado'),
        new OA\Property(property: 'oab_number', type: 'string', pattern: '^\d{1,8}$', nullable: true, description: 'Obrigatório para advogado; ignorado para comum', example: '123456'),
        new OA\Property(property: 'oab_uf', type: 'string', minLength: 2, maxLength: 2, nullable: true, description: 'UF brasileira. Obrigatório para advogado; ignorado para comum', example: 'BA'),
    ],
)]
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if (is_string($this->input('email'))) {
            $data['email'] = mb_strtolower(trim($this->input('email')));
        }
        // Only strip the mask of something shaped like a CPF; garbage is left
        // untouched so it fails with "CPF inválido." instead of "obrigatório".
        if (is_string($this->input('cpf')) && preg_match('/^\s*\d{3}\.?\d{3}\.?\d{3}-?\d{2}\s*$/', $this->input('cpf'))) {
            $data['cpf'] = CpfHelper::normalize($this->input('cpf'));
        }
        if (is_string($this->input('oab_uf'))) {
            $data['oab_uf'] = mb_strtoupper(trim($this->input('oab_uf')));
        }
        // "comum" users: OAB fields are ignored.
        if ($this->input('type') !== UserType::Advogado->value) {
            $data['oab_number'] = null;
            $data['oab_uf'] = null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $isLawyer = $this->input('type') === UserType::Advogado->value;

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'cpf' => ['required', 'string', 'bail', new Cpf, Rule::unique('users', 'cpf')],
            'type' => ['required', 'string', 'bail', $this->typeRule()],
            'oab_number' => $isLawyer
                ? ['required', 'bail', new Oab(Oab::NUMBER),
                    Rule::unique('users', 'oab_number')->where('oab_uf', $this->input('oab_uf'))]
                : ['nullable'],
            'oab_uf' => $isLawyer ? ['required', 'bail', new Oab(Oab::UF)] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
            'cpf.unique' => 'Este CPF já está cadastrado.',
            'oab_number.required' => 'O número da OAB é obrigatório para advogados.',
            'oab_uf.required' => 'A UF da OAB é obrigatória para advogados.',
            'oab_number.unique' => 'Esta inscrição na OAB já está cadastrada.',
        ];
    }

    /** Only "advogado" or "comum"; "empresa" has its own message (D1). */
    private function typeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (in_array($value, UserType::RESERVED, true)) {
                $fail('Cadastro de empresas ainda não disponível.');

                return;
            }

            $allowed = array_map(fn (UserType $t) => $t->value, UserType::selfRegistrable());

            if (! in_array($value, $allowed, true)) {
                $fail('O tipo deve ser "advogado" ou "comum".');
            }
        };
    }
}
