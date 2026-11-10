<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimulatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'in:aprovado,recusado'],
        ];
    }

    public function messages(): array
    {
        return ['outcome.in' => 'Resultado inválido. Use: aprovado ou recusado.'];
    }
}
