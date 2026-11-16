<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI 3 root: info, security schemes, reusable schemas and responses.
 * Operations are documented on the controllers.
 */
#[OA\OpenApi(openapi: '3.0.0')]
#[OA\Info(
    version: '1.0.0',
    title: 'Veridit — Commerce Service API',
    description: "Catálogo de pacotes, compra, pagamento e saldo de créditos do Veridit (REQ05–REQ07, D2).\n\n"
        ."**Como autenticar:** o token vem do **Identity** (`POST http://localhost:8000/api/v1/auth/login`). "
        ."Copie o `access_token` e clique em **Authorize** → `bearerAuth` (cole só o token, sem `Bearer`).\n\n"
        ."**Fluxo de compra:** `GET /packages` → `POST /orders` → pagamento (no simulador: `POST /orders/{id}/simulate`) "
        ."→ o provedor confirma por webhook → créditos no `GET /wallet`.\n\n"
        ."**Regras:** Básico 10 créditos, Médio 25, Premium 50 · imagem custa 1 crédito, vídeo 2 · créditos não expiram · "
        .'sem estorno nesta versão. Localmente a API roda em HTTP; em produção real, HTTPS (ADR-001).',
)]
#[OA\Server(url: '/', description: 'Servidor atual')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Access token emitido pelo Identity (RS256).',
)]
#[OA\SecurityScheme(
    securityScheme: 'serviceToken',
    type: 'apiKey',
    in: 'header',
    name: 'X-Service-Token',
    description: 'Token de serviço (COMMERCE_SERVICE_TOKEN). Só para outros serviços do backend, nunca para o front-end.',
)]
#[OA\SecurityScheme(
    securityScheme: 'simulatorSignature',
    type: 'apiKey',
    in: 'header',
    name: 'X-Simulator-Signature',
    description: 'HMAC-SHA256 (hex) do corpo bruto com PAYMENTS_SIMULATOR_WEBHOOK_SECRET.',
)]
#[OA\SecurityScheme(
    securityScheme: 'mercadoPagoSignature',
    type: 'apiKey',
    in: 'header',
    name: 'x-signature',
    description: 'Assinatura do Mercado Pago: "ts=...,v1=..." (HMAC-SHA256 com a chave secreta do webhook).',
)]
#[OA\Tag(name: 'Saúde', description: 'Disponibilidade da API')]
#[OA\Tag(name: 'Catálogo', description: 'Pacotes (D2) e custo das capturas')]
#[OA\Tag(name: 'Pedidos', description: 'Compra de pacotes (REQ05)')]
#[OA\Tag(name: 'Pagamentos', description: 'Simulador e webhooks dos provedores (REQ06, ADR-006)')]
#[OA\Tag(name: 'Carteira', description: 'Saldo e extrato de créditos')]
#[OA\Tag(name: 'Interno', description: 'Contratos entre serviços (Evidence)')]

// ---- Schemas -------------------------------------------------------------

#[OA\Schema(
    schema: 'Package',
    required: ['code', 'name', 'credits', 'price_cents', 'price', 'price_per_credit'],
    properties: [
        new OA\Property(property: 'code', type: 'string', enum: ['basico', 'medio', 'premium'], example: 'medio'),
        new OA\Property(property: 'name', type: 'string', example: 'Médio'),
        new OA\Property(property: 'credits', type: 'integer', example: 25),
        new OA\Property(property: 'price_cents', type: 'integer', example: 6500),
        new OA\Property(property: 'price', type: 'string', example: 'R$ 65,00'),
        new OA\Property(property: 'price_per_credit', type: 'string', example: 'R$ 2,60'),
    ],
)]
#[OA\Schema(
    schema: 'Order',
    required: ['id', 'user_id', 'package', 'amount_cents', 'amount', 'status', 'payment', 'billing', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '01a1c715-2288-70be-853d-1f71581be333'),
        new OA\Property(property: 'user_id', type: 'string', format: 'uuid', example: '01a1c556-b752-722d-a4e1-41f909b8bad6'),
        new OA\Property(property: 'package', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'medio'),
            new OA\Property(property: 'credits', type: 'integer', example: 25),
        ], type: 'object'),
        new OA\Property(property: 'amount_cents', type: 'integer', example: 6500),
        new OA\Property(property: 'amount', type: 'string', example: 'R$ 65,00'),
        new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'pago', 'falhou'], example: 'pendente'),
        new OA\Property(property: 'payment', properties: [
            new OA\Property(property: 'method', type: 'string', enum: ['simulador', 'mercadopago', 'pix'], example: 'simulador'),
            new OA\Property(property: 'provider_payment_id', type: 'string', nullable: true, example: 'sim_6f1c2a4e-3b8d-4f0e-9a51-2c7d8e9f0a1b'),
            new OA\Property(property: 'data', type: 'object', nullable: true,
                description: 'Dados do provedor. **pix**: `qr_code` (copia e cola), `qr_code_image` (PNG em data URI, use direto no `<img src>`), '
                    .'`ticket_url`, `expires_at`, `instructions`. **mercadopago**: `checkout_url`, `sandbox_checkout_url`, `instructions`. '
                    .'**simulador**: `instructions`. Depois do pagamento, inclui `paid_payment_id`.',
                example: [
                    'qr_code' => '00020126580014br.gov.bcb.pix0136...5204000053039865406065.005802BR...6304ABCD',
                    'qr_code_image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/123456789/ticket',
                    'expires_at' => '2026-11-16T15:30:00-03:00',
                    'instructions' => 'Abra o app do seu banco, escolha pagar com PIX e escaneie o QR Code (ou use o "PIX copia e cola"). Total: R$ 65,00. ...',
                ]),
        ], type: 'object'),
        new OA\Property(property: 'billing', description: 'Faturamento (T05): só dados do cadastro no Identity; CPF mascarado.', properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Maria Souza'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria@exemplo.com'),
            new OA\Property(property: 'cpf', type: 'string', nullable: true, example: '***.982.247-**'),
        ], type: 'object'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-11-10T20:10:00+00:00'),
    ],
)]
#[OA\Schema(
    schema: 'LedgerEntry',
    description: 'Lançamento imutável do extrato.',
    required: ['id', 'type', 'amount', 'balance_after', 'reason', 'reference', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', enum: ['credito', 'debito'], example: 'credito'),
        new OA\Property(property: 'amount', type: 'integer', example: 25),
        new OA\Property(property: 'balance_after', type: 'integer', example: 25),
        new OA\Property(property: 'reason', type: 'string', enum: ['compra', 'captura_imagem', 'captura_video'], example: 'compra'),
        new OA\Property(property: 'reference', properties: [
            new OA\Property(property: 'type', type: 'string', enum: ['order', 'capture'], example: 'order'),
            new OA\Property(property: 'id', type: 'string', example: '01a1c715-2288-70be-853d-1f71581be333'),
        ], type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
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
            example: ['package' => ['Pacote inválido. Use: basico, medio ou premium.']],
        ),
    ],
)]
#[OA\Schema(
    schema: 'Error',
    required: ['message'],
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Não autenticado.')],
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
    description: 'Não autenticado: token ausente, inválido, expirado ou não assinado pelo Identity.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Não autenticado.']),
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Sem permissão para este recurso.',
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
    response: 'Conflict',
    description: 'Conflito com o estado atual (ex.: pedido já finalizado).',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Este pedido já foi finalizado.']),
)]
final class ApiSpec {}
