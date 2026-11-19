<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters of GET /users (Consulta 2). */
class ListUsersRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 50;

    public function authorize(): bool
    {
        return true; // access control: "type:admin" middleware + UserPolicy
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(UserType::values())],
            'status' => ['sometimes', 'string', Rule::in(UserStatus::values())],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.max' => 'O campo itens por página não pode ser maior que '.self::MAX_PER_PAGE.'.',
        ];
    }
}
