<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookException;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Models\Order;
use App\Payments\Charge;
use App\Payments\PaymentGateway;
use App\Payments\PaymentStatus;
use App\Payments\ProviderEvent;
use App\Support\Money;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Mercado Pago — Checkout Pro (ADR-006). The buyer pays on Mercado Pago's page
 * (card, account balance or PIX).
 *
 * - createCharge: POST /checkout/preferences, external_reference = our order id.
 * - webhook: validates "x-signature" (HMAC-SHA256 of "id:{data.id};request-id:{x-request-id};ts:{ts};"
 *   with the webhook secret), then READS the payment from the API
 *   (GET /v1/payments/{id}) — the webhook body is never trusted for status/amount.
 */
class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(
        protected readonly HttpClient $http,
        protected readonly array $config,
    ) {}

    public function method(): PaymentMethod
    {
        return PaymentMethod::MercadoPago;
    }

    public function createCharge(Order $order): Charge
    {
        $payload = [
            'items' => [[
                'id' => $order->package_code,
                'title' => "Veridit — pacote {$order->package_code} ({$order->credits} créditos)",
                'quantity' => 1,
                'unit_price' => round($order->amount_cents / 100, 2),
                'currency_id' => 'BRL',
            ]],
            'payer' => ['name' => $order->billing_name, 'email' => $order->billing_email],
            'external_reference' => $order->id,
            'notification_url' => $this->config['notification_url'],
            'statement_descriptor' => 'VERIDIT',
            'metadata' => ['order_id' => $order->id],
        ];

        if (! empty($this->config['back_url'])) {
            $back = rtrim($this->config['back_url'], '/');
            $payload['back_urls'] = [
                'success' => "{$back}?pedido={$order->id}&status=aprovado",
                'pending' => "{$back}?pedido={$order->id}&status=pendente",
                'failure' => "{$back}?pedido={$order->id}&status=recusado",
            ];
            if (str_starts_with($back, 'https://')) {
                $payload['auto_return'] = 'approved';
            }
        }

        $response = $this->api()
            ->withHeaders(['X-Idempotency-Key' => $order->id])
            ->post('/checkout/preferences', $payload);

        if (! $response->successful() || ! $response->json('id') || ! $response->json('init_point')) {
            throw new RuntimeException('Mercado Pago recusou a preferência: HTTP '.$response->status());
        }

        return new Charge((string) $response->json('id'), [
            'checkout_url' => $response->json('init_point'),
            'sandbox_checkout_url' => $response->json('sandbox_init_point'),
            'instructions' => 'Abra checkout_url para pagar com cartão, saldo Mercado Pago ou PIX. Total: '.Money::brl($order->amount_cents).'.',
        ]);
    }

    public function parseWebhook(Request $request): ?ProviderEvent
    {
        $query = $this->rawQuery($request);
        $body = $request->json()->all();

        $dataId = (string) ($query['data.id'] ?? $body['data']['id'] ?? '');
        $type = (string) ($query['type'] ?? $body['type'] ?? $query['topic'] ?? '');

        $this->assertSignature($request, $dataId);

        // Other topics (merchant_order, plan, ...) are acknowledged and ignored.
        if ($type !== 'payment' || ! preg_match('/^\d{1,30}$/', $dataId)) {
            return null;
        }

        $payment = $this->fetchPayment($dataId);

        if ($payment === null) {
            return null; // e.g. the "test notification" sent from Mercado Pago's panel
        }

        $status = match ($payment['status'] ?? null) {
            'approved' => PaymentStatus::Approved,
            'rejected', 'cancelled' => PaymentStatus::Rejected,
            default => PaymentStatus::Pending, // pending, in_process, authorized, in_mediation, refunded, charged_back
        };

        $amountCents = ($payment['currency_id'] ?? null) === 'BRL' && isset($payment['transaction_amount'])
            ? (int) round(((float) $payment['transaction_amount']) * 100)
            : -1; // unknown currency/amount never matches an order

        return new ProviderEvent(
            provider: PaymentMethod::MercadoPago,
            eventId: "{$dataId}:".($payment['status'] ?? 'desconhecido'),
            providerPaymentId: $dataId,
            status: $status,
            payload: [
                'payment_id' => $dataId,
                'status' => $payment['status'] ?? null,
                'status_detail' => $payment['status_detail'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'transaction_amount' => $payment['transaction_amount'] ?? null,
                'external_reference' => $payment['external_reference'] ?? null,
            ],
            orderId: isset($payment['external_reference']) ? (string) $payment['external_reference'] : '',
            amountCents: $amountCents,
        );
    }

    private function assertSignature(Request $request, string $dataId): void
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $header = (string) $request->header('x-signature');

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';

        if ($secret === '' || $ts === '' || $v1 === '' || $dataId === '') {
            throw new InvalidWebhookException;
        }

        $manifest = 'id:'.strtolower($dataId).';';
        if ($requestId = $request->header('x-request-id')) {
            $manifest .= "request-id:{$requestId};";
        }
        $manifest .= "ts:{$ts};";

        if (! hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($v1))) {
            throw new InvalidWebhookException;
        }
    }

    /** @return ?array<string, mixed> null when Mercado Pago does not know the payment */
    private function fetchPayment(string $paymentId): ?array
    {
        try {
            $response = $this->api()->get("/v1/payments/{$paymentId}");
        } catch (Throwable $e) {
            throw new PaymentProviderUnavailableException('mercadopago', $e::class);
        }

        if ($response->status() === 404) {
            Log::info('Mercado Pago: notificação de pagamento inexistente ignorada.', ['payment_id' => $paymentId]);

            return null;
        }
        if (! $response->successful()) {
            throw new PaymentProviderUnavailableException('mercadopago', 'HTTP '.$response->status());
        }

        return $response->json();
    }

    protected function api(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->config['api_url'], '/'))
            ->withToken((string) $this->config['access_token'])
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 10));
    }

    /**
     * Query string as sent (PHP turns "data.id" into "data_id"; Mercado Pago signs "data.id").
     *
     * @return array<string, string>
     */
    private function rawQuery(Request $request): array
    {
        $query = [];
        foreach (explode('&', (string) $request->server('QUERY_STRING')) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $query[urldecode($key)] = urldecode($value);
        }

        return $query;
    }
}
