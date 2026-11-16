<?php

namespace App\Http\Requests;

use App\Enums\CaptureType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Contract with Evidence: debit credits for a capture (idempotent by capture_id). */
class ConsumeCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by the service token middleware
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'capture_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
            'capture_type' => ['required', 'string', Rule::enum(CaptureType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'capture_type.enum' => 'Tipo de captura inválido. Use: imagem ou video.',
        ];
    }

    public function captureType(): CaptureType
    {
        return CaptureType::from($this->validated('capture_type'));
    }
}
