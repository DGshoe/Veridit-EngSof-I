<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    public function __construct(private readonly UserQueryService $users) {}

    /** Consulta 1 — the authenticated user. */
    #[OA\Get(
        path: '/api/v1/me',
        operationId: 'me',
        summary: 'Dados do usuário logado (Consulta 1)',
        security: [['bearerAuth' => []]],
        tags: ['Usuários'],
        responses: [
            new OA\Response(response: 200, description: 'Usuário autenticado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Consulta 2 — paginated, filterable list (admin). */
    #[OA\Get(
        path: '/api/v1/users',
        operationId: 'listUsers',
        summary: 'Listar usuários (Consulta 2, só admin)',
        description: 'Paginado, ordenado por data de cadastro (mais recentes primeiro).',
        security: [['bearerAuth' => []]],
        tags: ['Usuários'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['advogado', 'comum', 'admin'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ativo', 'bloqueado'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Parte do nome ou do e-mail (sem diferenciar maiúsculas)', schema: new OA\Schema(type: 'string', maxLength: 100), example: 'maria'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 50, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Página de usuários', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(ListUsersRequest $request): UserCollection
    {
        Gate::authorize('viewAny', User::class);

        return new UserCollection($this->users->paginate($request->validated()));
    }

    /** Detail — admin, or the user themself. */
    #[OA\Get(
        path: '/api/v1/users/{id}',
        operationId: 'showUser',
        summary: 'Detalhe de um usuário (admin ou o próprio usuário)',
        security: [['bearerAuth' => []]],
        tags: ['Usuários'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuário', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }
}
