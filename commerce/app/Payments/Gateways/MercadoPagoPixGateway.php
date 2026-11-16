<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Payments\Charge;
use App\Support\Money;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * PIX with the QR Code shown by Veridit itself (ADR-006: PIX/QR through a PSP).
 *
 * - createCharge: POST /v1/payments with payment_method_id = "pix". Mercado Pago returns the
 *   "copia e cola" code and the QR Code image; the buyer never leaves Veridit.
 * - webhook: the same Mercado Pago webhook (signature + payment read from the API), inherited.
 *   Expired QR Codes become "cancelled" at Mercado Pago, which fails the order.
 */
class MercadoPagoPixGateway extends MercadoPagoGateway
{
    /** Mercado Pago accepts a PIX expiration between 30 minutes and 30 days. */
    private const MIN_MINUTES = 30;

    private const MAX_MINUTES = 30 * 24 * 60;

    public function method(): PaymentMethod
    {
        return PaymentMethod::Pix;
    }

    public function createCharge(Order $order): Charge
    {
        $minutes = min(self::MAX_MINUTES, max(self::MIN_MINUTES, (int) ($this->config['pix_expiration_minutes'] ?? self::MIN_MINUTES)));
        $expiresAt = Carbon::now('America/Sao_Paulo')->addMinutes($minutes)->startOfSecond();

        [$firstName, $lastName] = $this->splitName((string) $order->billing_name);

        $response = $this->api()
            ->withHeaders(['X-Idempotency-Key' => "pix-{$order->id}"])
            ->post('/v1/payments', [
                'transaction_amount' => round($order->amount_cents / 100, 2),
                'description' => "Veridit — pacote {$order->package_code} ({$order->credits} créditos)",
                'payment_method_id' => 'pix',
                'payer' => array_filter([
                    'email' => $order->billing_email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]),
                'external_reference' => $order->id,
                'notification_url' => $this->config['notification_url'],
                'date_of_expiration' => $expiresAt->format('Y-m-d\TH:i:s.vP'),
                'statement_descriptor' => 'VERIDIT',
                'metadata' => ['order_id' => $order->id],
            ]);

        $qrCode = $response->json('point_of_interaction.transaction_data.qr_code');
        $qrImage = $response->json('point_of_interaction.transaction_data.qr_code_base64');

        if (! $response->successful() || ! $response->json('id') || ! $qrCode || ! $qrImage) {
            throw new RuntimeException('Mercado Pago recusou a cobrança PIX: HTTP '.$response->status());
        }

        return new Charge((string) $response->json('id'), [
            'qr_code' => $qrCode,
            'qr_code_image' => "data:image/png;base64,{$qrImage}",
            'ticket_url' => $response->json('point_of_interaction.transaction_data.ticket_url'),
            'expires_at' => $expiresAt->toIso8601String(),
            'instructions' => 'Abra o app do seu banco, escolha pagar com PIX e escaneie o QR Code '
                .'(ou use o "PIX copia e cola"). Total: '.Money::brl($order->amount_cents)
                .'. Válido até '.$expiresAt->format('d/m/Y H:i').' (horário de Brasília). '
                .'Os créditos entram assim que o banco confirmar o pagamento.',
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [($parts[0] ?? '') ?: null, ($parts[1] ?? '') ?: null];
    }
}
