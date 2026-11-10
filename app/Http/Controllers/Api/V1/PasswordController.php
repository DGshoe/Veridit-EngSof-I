<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    /** REQ02 — always the same answer, whether the e-mail exists or not. */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwords->requestLink($request->validated('email'));

        return response()->json(['message' => __('passwords.sent')]);
    }

    /** REQ02 */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwords->reset(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        );

        return response()->json(['message' => 'Senha redefinida com sucesso. Faça login com a nova senha.']);
    }
}
