<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

/** REQ02 */
#[OA\Schema(
    schema: 'ResetPasswordRequest',
    required: ['token', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'token', type: 'string', description: 'Token recebido no link do e-mail (expira em 60 min, uso único)', example: '70b0c1fe486afb13...'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria@exemplo.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'Mínimo 8 caracteres, com letras e números', example: 'NovaSenha123'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NovaSenha123'),
    ],
)]
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
