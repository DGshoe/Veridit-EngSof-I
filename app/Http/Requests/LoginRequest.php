<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** REQ03 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
