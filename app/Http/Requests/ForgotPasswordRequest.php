<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** REQ02 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:150'],
        ];
    }
}
