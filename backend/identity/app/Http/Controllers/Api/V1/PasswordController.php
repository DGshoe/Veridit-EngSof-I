<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    /** REQ02 — always the same answer, whether the e-mail exists or not. */
    #[OA\Post(
        path: '/api/v1/auth/forgot-password',
        operationId: 'forgotPassword',
        summary: 'Solicitar recuperação de senha (REQ02)',
        description: 'Envia por e-mail o link `FRONTEND_URL/redefinir-senha?token=...&email=...` (expira em 60 min). '
            .'Responde sempre 200 com a mesma mensagem, exista ou não o e-mail. Limite: 3 por minuto.',
        tags: ['Senha'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Solicitação aceita', content: new OA\JsonContent(
                ref: '#/components/schemas/Message',
                example: ['message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.'],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwords->requestLink($request->validated('email'));

        return response()->json(['message' => __('passwords.sent')]);
    }

    /** REQ02 */
    #[OA\Post(
        path: '/api/v1/auth/reset-password',
        operationId: 'resetPassword',
        summary: 'Redefinir a senha com o token (REQ02)',
        description: 'Define a nova senha, consome o token e invalida **todos** os JWT emitidos antes da troca.',
        tags: ['Senha'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Senha redefinida', content: new OA\JsonContent(
                ref: '#/components/schemas/Message',
                example: ['message' => 'Senha redefinida com sucesso. Faça login com a nova senha.'],
            )),
            new OA\Response(response: 422, description: 'Token inválido/expirado ou senha fraca',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError', example: [
                    'message' => 'Os dados enviados são inválidos.',
                    'errors' => ['token' => ['Token de redefinição inválido ou expirado.']],
                ])),
        ],
    )]
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
