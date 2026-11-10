<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /** REQ01 */
    #[OA\Post(
        path: '/api/v1/auth/register',
        operationId: 'register',
        summary: 'Cadastrar advogado ou usuário comum (REQ01)',
        description: 'Cria a conta. **Não** faz login automático. "admin" só via seeder; "empresa" ainda não disponível.',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Usuário cadastrado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                new OA\Property(property: 'message', type: 'string', example: 'Usuário cadastrado com sucesso.'),
            ])),
            new OA\Response(response: 422, description: 'Dados inválidos (CPF inválido, e-mail/CPF/OAB já cadastrado, OAB ausente para advogado, tipo "empresa"...)',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError', examples: [
                    new OA\Examples(example: 'cpf', summary: 'CPF inválido', value: ['message' => 'Os dados enviados são inválidos.', 'errors' => ['cpf' => ['CPF inválido.']]]),
                    new OA\Examples(example: 'empresa', summary: 'Tipo empresa', value: ['message' => 'Os dados enviados são inválidos.', 'errors' => ['type' => ['Cadastro de empresas ainda não disponível.']]]),
                    new OA\Examples(example: 'oab', summary: 'Advogado sem OAB', value: ['message' => 'Os dados enviados são inválidos.', 'errors' => ['oab_number' => ['O número da OAB é obrigatório para advogados.']]]),
                ])),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Usuário cadastrado com sucesso.'])
            ->response()
            ->setStatusCode(201);
    }

    /** REQ03 */
    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'login',
        summary: 'Login (REQ03)',
        description: 'Retorna o access token JWT (15 min). Limite: 5 tentativas por minuto por IP + e-mail.',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Autenticado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TokenResponse'),
            ])),
            new OA\Response(response: 401, description: 'Credenciais inválidas (mensagem genérica)',
                content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'E-mail ou senha inválidos.'])),
            new OA\Response(response: 403, description: 'Usuário bloqueado',
                content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Usuário bloqueado. Entre em contato com o suporte.'])),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function login(LoginRequest $request): TokenResource
    {
        return new TokenResource(
            $this->auth->login($request->validated('email'), $request->validated('password'))
        );
    }

    /** REQ04 */
    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'logout',
        summary: 'Logout (REQ04)',
        description: 'Invalida o token atual (blacklist). Usar o mesmo token de novo resulta em 401.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 204, description: 'Token invalidado'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function logout(): Response
    {
        $this->auth->logout();

        return response()->noContent();
    }

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        operationId: 'refresh',
        summary: 'Renovar o token',
        description: 'Troca o token atual por um novo. Aceita token já expirado, desde que dentro da janela de 7 dias após o login. O token antigo vai para a blacklist.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Novo token', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TokenResponse'),
            ])),
            new OA\Response(response: 401, description: 'Token ausente, inválido, revogado ou fora da janela de refresh',
                content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Token inválido ou expirado.'])),
            new OA\Response(response: 403, description: 'Usuário bloqueado',
                content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Usuário bloqueado. Entre em contato com o suporte.'])),
        ],
    )]
    public function refresh(): TokenResource
    {
        return new TokenResource($this->auth->refresh());
    }
}
