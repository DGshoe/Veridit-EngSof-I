<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookException;
use App\Models\Order;
use App\Payments\Charge;
use App\Payments\PaymentGateway;
use App\Payments\PaymentStatus;
use App\Payments\ProviderEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Fake provider for tests and demos (T06 "Simular confirmação / falha").
 * Behaves like a real PSP: the confirmation arrives as a signed webhook
 * (header X-Simulator-Signature = HMAC-SHA256 of the raw body).
 */
class SimulatorGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Simulator-Signature';

    public function __construct(private readonly ?string $webhookSecret) {}

    public function method(): PaymentMethod
    {
        return PaymentMethod::Simulator;
    }

    public function createCharge(Order $order): Charge
    {
        return new Charge('sim_'.Str::uuid(), [
            'instructions' => 'Ambiente de simulação: nenhum valor é cobrado. Use "Simular confirmação" ou "Simular falha".',
        ]);
    }

    public function parseWebhook(Request $request): ProviderEvent
    {
        $body = $request->getContent();
        $signature = (string) $request->header(self::SIGNATURE_HEADER);

        if (! $this->webhookSecret || $signature === '' || ! hash_equals($this->sign($body), $signature)) {
            throw new InvalidWebhookException;
        }

        $data = json_decode($body, true);
        $status = is_array($data) ? PaymentStatus::tryFrom((string) ($data['status'] ?? '')) : null;

        if (! $status || empty($data['event_id']) || empty($data['payment_id'])
            || ! is_string($data['event_id']) || ! is_string($data['payment_id'])) {
            throw new InvalidWebhookException;
        }

        return new ProviderEvent(PaymentMethod::Simulator, $data['event_id'], $data['payment_id'], $status, $data);
    }

    /** Builds the signed notification exactly as the simulated provider would send it. */
    public function notification(string $paymentId, PaymentStatus $status, ?string $eventId = null): array
    {
        $body = json_encode([
            'event_id' => $eventId ?? 'evt_'.Str::uuid(),
            'payment_id' => $paymentId,
            'status' => $status->value,
        ], JSON_THROW_ON_ERROR);

        return ['body' => $body, 'signature' => $this->sign($body)];
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) $this->webhookSecret);
    }
}
