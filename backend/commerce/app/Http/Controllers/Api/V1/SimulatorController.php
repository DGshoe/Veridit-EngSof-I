<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\SimulatePaymentRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Payments\Gateways\SimulatorGateway;
use App\Payments\PaymentEventProcessor;
use App\Payments\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * T06 "Simular confirmação / Simular falha". The simulated provider sends a
 * signed notification that goes through the SAME path as real webhooks.
 */
class SimulatorController extends Controller
{
    public function __construct(
        private readonly SimulatorGateway $simulator,
        private readonly PaymentEventProcessor $processor,
    ) {}

    #[OA\Post(
        path: '/api/v1/orders/{id}/simulate',
        operationId: 'simulatePayment',
        summary: 'Simular confirmação ou falha do pagamento (T06)',
        description: 'Só existe com `PAYMENTS_SIMULATOR_ENABLED=true` (nunca em produção real) e só para o **dono** do pedido. '
            .'O simulador envia uma notificação assinada que passa pelo mesmo processamento dos webhooks reais.',
        security: [['bearerAuth' => []]],
        tags: ['Pagamentos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['outcome'],
            properties: [new OA\Property(property: 'outcome', type: 'string', enum: ['aprovado', 'recusado'], example: 'aprovado')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Pagamento processado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
                new OA\Property(property: 'message', type: 'string', example: 'Pagamento confirmado. Créditos adicionados.'),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(response: 404, description: 'Pedido inexistente ou simulador desligado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(ref: '#/components/responses/Conflict', response: 409),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function simulate(SimulatePaymentRequest $request, Order $order): JsonResponse
    {
        abort_unless(PaymentMethod::Simulator->isAvailable(), 404);
        Gate::authorize('pay', $order);

        if ($order->payment_method !== PaymentMethod::Simulator || ! $order->provider_payment_id) {
            return response()->json(['message' => 'Este pedido não usa o simulador de pagamento.'], 409);
        }
        if ($order->status->isFinal()) {
            return response()->json(['message' => 'Este pedido já foi finalizado.'], 409);
        }

        $status = $request->validated('outcome') === 'aprovado' ? PaymentStatus::Approved : PaymentStatus::Rejected;
        $notification = $this->simulator->notification($order->provider_payment_id, $status);

        $event = $this->simulator->parseWebhook($this->asWebhook($notification));
        $this->processor->process($event);

        return response()->json([
            'data' => new OrderResource($order->refresh()),
            'message' => $status === PaymentStatus::Approved ? 'Pagamento confirmado. Créditos adicionados.' : 'Pagamento recusado.',
        ]);
    }

    /** Webhook of the simulated provider (signature X-Simulator-Signature). */
    #[OA\Post(
        path: '/api/v1/webhooks/simulator',
        operationId: 'simulatorWebhook',
        summary: 'Webhook do provedor simulado',
        description: 'Chamado pelo provedor, não pelo front-end. Idempotente por `event_id`: a mesma notificação entregue '
            .'várias vezes credita **uma única vez** (`result: duplicado`). Responde 200 também para pagamentos '
            .'desconhecidos ou pedidos já finalizados (`result: ignorado`), para o provedor parar de reenviar.',
        security: [['simulatorSignature' => []]],
        tags: ['Pagamentos'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['event_id', 'payment_id', 'status'],
            properties: [
                new OA\Property(property: 'event_id', type: 'string', example: 'evt_0d6f2c1e-8a4b-4c3d-9e2f-1a2b3c4d5e6f'),
                new OA\Property(property: 'payment_id', type: 'string', example: 'sim_6f1c2a4e-3b8d-4f0e-9a51-2c7d8e9f0a1b'),
                new OA\Property(property: 'status', type: 'string', enum: ['aprovado', 'recusado', 'pendente'], example: 'aprovado'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Notificação recebida', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Notificação recebida.'),
                    new OA\Property(property: 'result', type: 'string', enum: ['aplicado', 'ignorado', 'duplicado'], example: 'aplicado'),
                ],
            )),
            new OA\Response(response: 401, description: 'Assinatura ausente ou inválida', content: new OA\JsonContent(ref: '#/components/schemas/Error',
                example: ['message' => 'Notificação inválida.'])),
            new OA\Response(response: 404, description: 'Simulador desligado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function webhook(Request $request): JsonResponse
    {
        abort_unless(PaymentMethod::Simulator->isAvailable(), 404);

        $result = $this->processor->process($this->simulator->parseWebhook($request));

        return response()->json(['message' => 'Notificação recebida.', 'result' => $result->value]);
    }

    /** @param array{body: string, signature: string} $notification */
    private function asWebhook(array $notification): Request
    {
        $request = Request::create('/api/v1/webhooks/simulator', 'POST', content: $notification['body']);
        $request->headers->set(SimulatorGateway::SIGNATURE_HEADER, $notification['signature']);

        return $request;
    }
}
