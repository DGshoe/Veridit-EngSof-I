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
