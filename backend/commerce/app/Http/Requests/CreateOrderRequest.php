<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'package' => is_string($this->input('package')) ? mb_strtolower(trim($this->input('package'))) : $this->input('package'),
            'payment_method' => is_string($this->input('payment_method')) ? mb_strtolower(trim($this->input('payment_method'))) : $this->input('payment_method'),
        ]);
    }

    public function rules(): array
    {
        return [
            'package' => ['required', 'string', Rule::exists('packages', 'code')->where('active', true)],
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('payment_method')) {
                return;
            }
            $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));
            if ($method && ! $method->isAvailable()) {
                $validator->errors()->add('payment_method', 'Forma de pagamento ainda não disponível.');
            }
        }, function (Validator $validator) {
            $key = $this->header('Idempotency-Key');
            if ($key !== null && ! preg_match('/^[A-Za-z0-9_\-:.]{8,100}$/', $key)) {
                $validator->errors()->add('idempotency_key', 'O cabeçalho Idempotency-Key deve ter de 8 a 100 caracteres (letras, números, - _ : .).');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'package.exists' => 'Pacote inválido. Use: basico, medio ou premium.',
            'payment_method.enum' => 'Forma de pagamento inválida.',
        ];
    }

    public function idempotencyKey(): ?string
    {
        return $this->header('Idempotency-Key') ?: null;
    }
}
