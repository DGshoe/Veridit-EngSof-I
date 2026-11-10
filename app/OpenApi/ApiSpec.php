<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI 3 root definition: info, server, security scheme, reusable schemas
 * and responses. Operations are documented on the controllers.
 */
#[OA\OpenApi(openapi: '3.0.0')]
#[OA\Info(
    version: '1.0.0',
    title: 'Veridit — Identity Service API',
    description: "Cadastro, autenticação (JWT) e perfis de usuários do Veridit (REQ01–REQ04).\n\n"
        ."**Como autenticar:** faça `POST /api/v1/auth/login`, copie o `access_token` e clique em **Authorize** "
        ."(cole só o token, sem o prefixo `Bearer`). O token vale 15 minutos; renove com `POST /api/v1/auth/refresh` "
        ."em até 7 dias após o login.\n\n"
        .'Localmente a API roda em HTTP; em produção real deve ficar atrás de HTTPS (ADR-001).',
)]
#[OA\Server(url: '/', description: 'Servidor atual')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Access token JWT obtido em /api/v1/auth/login.',
)]
#[OA\Tag(name: 'Saúde', description: 'Disponibilidade da API')]
#[OA\Tag(name: 'Autenticação', description: 'Cadastro, login, logout e renovação de token')]
#[OA\Tag(name: 'Senha', description: 'Recuperação e redefinição de senha')]
#[OA\Tag(name: 'Usuários', description: 'Consultas de usuários')]

// ---- Schemas -------------------------------------------------------------

#[OA\Schema(
    schema: 'User',
    description: 'Usuário. O CPF é sempre mascarado e a senha nunca é retornada.',
    required: ['id', 'name', 'email', 'cpf', 'type', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '01a1c567-3767-70b9-8963-b66b74b46d7b'),
        new OA\Property(property: 'name', type: 'string', example: 'Maria Souza'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria@exemplo.com'),
        new OA\Property(property: 'cpf', type: 'string', description: 'CPF mascarado', example: '***.982.247-**'),
        new OA\Property(property: 'type', type: 'string', enum: ['advogado', 'comum', 'admin'], example: 'advogado'),
        new OA\Property(property: 'oab', type: 'string', nullable: true, description: 'Número/UF (só advogados)', example: '123456/BA'),
        new OA\Property(property: 'status', type: 'string', enum: ['ativo', 'bloqueado'], example: 'ativo'),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true, example: '2026-11-10T12:20:45+00:00'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-11-10T12:20:38+00:00'),
    ],
)]
#[OA\Schema(
    schema: 'TokenResponse',
    required: ['access_token', 'token_type', 'expires_in', 'user'],
    properties: [
        new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', description: 'Validade do token em segundos', example: 900),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Os dados enviados são inválidos.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            description: 'Mensagens por campo',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
            example: ['cpf' => ['CPF inválido.']],
        ),
    ],
)]
#[OA\Schema(
    schema: 'Error',
    required: ['message'],
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Não autenticado.')],
)]
#[OA\Schema(
    schema: 'Message',
    required: ['message'],
    properties: [new OA\Property(property: 'message', type: 'string')],
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    required: ['current_page', 'per_page', 'total', 'last_page'],
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 42),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
    ],
)]

// ---- Reusable responses ----------------------------------------------------

#[OA\Response(
    response: 'Unauthorized',
    description: 'Não autenticado: token ausente, inválido, expirado, revogado (logout) ou emitido antes da última troca de senha.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Não autenticado.']),
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Perfil sem permissão para o recurso.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Acesso negado.']),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Recurso não encontrado.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Recurso não encontrado.']),
)]
#[OA\Response(
    response: 'ValidationFailed',
    description: 'Dados inválidos.',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
)]
#[OA\Response(
    response: 'TooManyRequests',
    description: 'Limite de tentativas excedido. Veja o cabeçalho Retry-After.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Muitas tentativas. Tente novamente em instantes.']),
)]
final class ApiSpec {}
