<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Payments\Gateways\MercadoPagoGateway;
use App\Payments\PaymentEventProcessor;
use App\Payments\ProcessingResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoGateway $gateway,
        private readonly PaymentEventProcessor $processor,
    ) {}

    #[OA\Post(
        path: '/api/v1/webhooks/mercadopago',
        operationId: 'mercadoPagoWebhook',
        summary: 'Webhook do Mercado Pago (REQ06)',
        description: 'Chamado pelo Mercado Pago, não pelo front-end. Cadastre esta URL (pública, HTTPS) em '
            .'**Suas integrações → Webhooks**, evento **Pagamentos**. A assinatura `x-signature` é validada com a chave secreta '
            .'do webhook e o status é **consultado na API do Mercado Pago** (o corpo do aviso não é confiável). '
            .'Idempotente: o mesmo pagamento no mesmo status credita uma única vez; o valor pago precisa ser igual ao do pedido.',
        security: [['mercadoPagoSignature' => []]],
        tags: ['Pagamentos'],
        parameters: [
            new OA\Parameter(name: 'data.id', in: 'query', required: false, description: 'Id do pagamento (enviado pelo Mercado Pago)',
                schema: new OA\Schema(type: 'string', example: '123456789')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'payment')),
            new OA\Parameter(name: 'x-request-id', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: [
            'action' => 'payment.updated', 'api_version' => 'v1', 'type' => 'payment', 'live_mode' => false,
            'data' => ['id' => '123456789'], 'date_created' => '2026-11-10T20:00:00Z', 'id' => 12345678901,
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Notificação recebida', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Notificação recebida.'),
                new OA\Property(property: 'result', type: 'string', enum: ['aplicado', 'ignorado', 'duplicado'], example: 'aplicado'),
            ])),
            new OA\Response(response: 401, description: 'Assinatura ausente ou inválida', content: new OA\JsonContent(ref: '#/components/schemas/Error',
                example: ['message' => 'Notificação inválida.'])),
            new OA\Response(response: 503, description: 'API do Mercado Pago indisponível (o Mercado Pago reenvia depois)',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $event = $this->gateway->parseWebhook($request);

        $result = $event ? $this->processor->process($event) : ProcessingResult::Ignored;

        return response()->json(['message' => 'Notificação recebida.', 'result' => $result->value]);
    }
}
