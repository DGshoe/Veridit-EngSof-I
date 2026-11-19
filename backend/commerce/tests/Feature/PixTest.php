<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OutboxMessage;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ADR-006 / REQ06 — PIX with the QR Code shown by Veridit (Mercado Pago payments API as the PSP),
 * with the HTTP API faked. Confirmation arrives at the same signed Mercado Pago webhook.
 */
class PixTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'mp-webhook-secret-de-teste';

    private const API = 'https://api.mercadopago.test';

    private const PAYMENT_ID = '1320001234';

    private const QR_CODE = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913VERIDIT6009SAO PAULO62070503***6304ABCD';

    private string $userId;

    private ?array $payment = null;

    private int $createHttpStatus = 201;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PackageSeeder::class);
        $this->userId = (string) Str::uuid();

        config(['commerce.payments.mercadopago' => [
            'access_token' => 'TEST-token-de-teste',
            'public_key' => 'TEST-public',
            'webhook_secret' => self::SECRET,
            'notification_url' => 'https://exemplo.ngrok.test/api/v1/webhooks/mercadopago',
            'back_url' => null,
            'api_url' => self::API,
            'timeout' => 5,
            'pix_enabled' => true,
            'pix_expiration_minutes' => 30,
        ]]);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'identity.test')) {
                return Http::response(['data' => ['id' => $this->userId, 'name' => 'Maria Souza Lima', 'email' => 'maria@exemplo.com', 'cpf' => '***.982.247-**']]);
            }
            if ($request->method() === 'POST' && $url === self::API.'/v1/payments') {
                return $this->createHttpStatus === 201
                    ? Http::response([
                        'id' => (int) self::PAYMENT_ID,
                        'status' => 'pending',
                        'status_detail' => 'pending_waiting_transfer',
                        'point_of_interaction' => ['transaction_data' => [
                            'qr_code' => self::QR_CODE,
                            'qr_code_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
                            'ticket_url' => 'https://www.mercadopago.com.br/payments/'.self::PAYMENT_ID.'/ticket',
                        ]],
                    ], 201)
                    : Http::response(['message' => 'erro'], $this->createHttpStatus);
            }
            if ($request->method() === 'GET' && $url === self::API.'/v1/payments/'.self::PAYMENT_ID) {
                return $this->payment ? Http::response($this->payment) : Http::response(['message' => 'not found'], 404);
            }

            return Http::response('rota inesperada no fake', 599);
        });
    }

    private function newPixOrder(string $package = 'medio')
    {
        return $this->postJson('/api/v1/orders', ['package' => $package, 'payment_method' => 'pix'], $this->asUser($this->userId));
    }

    private function paymentFor(Order $order, string $status, ?float $amount = null): void
    {
        $this->payment = [
            'id' => (int) self::PAYMENT_ID,
            'status' => $status,
            'status_detail' => match ($status) {
                'approved' => 'accredited',
                'cancelled' => 'expired',
                default => 'pending_waiting_transfer',
            },
            'external_reference' => $order->id,
            'transaction_amount' => $amount ?? $order->amount_cents / 100,
            'currency_id' => 'BRL',
            'payment_method_id' => 'pix',
        ];
    }

    private function notify()
    {
        $ts = (string) time();
        $requestId = 'req-pix-1';
        $signature = 'ts='.$ts.',v1='.hash_hmac('sha256', 'id:'.self::PAYMENT_ID.";request-id:{$requestId};ts:{$ts};", self::SECRET);

        return $this->call('POST', '/api/v1/webhooks/mercadopago?data.id='.self::PAYMENT_ID.'&type=payment', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_REQUEST_ID' => $requestId,
        ], content: json_encode(['action' => 'payment.updated', 'type' => 'payment', 'data' => ['id' => self::PAYMENT_ID]]));
    }

    private function balance(): int
    {
        return $this->getJson('/api/v1/wallet', $this->asUser($this->userId))->json('data.balance');
    }

    public function test_pix_order_returns_the_qr_code_generated_by_the_system(): void
    {
        $this->freezeSecond();

        $response = $this->newPixOrder()->assertCreated()
            ->assertJsonPath('data.status', 'pendente')
            ->assertJsonPath('data.payment.method', 'pix')
            ->assertJsonPath('data.payment.provider_payment_id', self::PAYMENT_ID)
            ->assertJsonPath('data.payment.data.qr_code', self::QR_CODE);

        $this->assertStringStartsWith('data:image/png;base64,iVBORw0KGgo', $response->json('data.payment.data.qr_code_image'));
        $this->assertStringContainsString('R$ 65,00', $response->json('data.payment.data.instructions'));
        $this->assertNotNull($response->json('data.payment.data.expires_at'));

        $orderId = $response->json('data.id');

        Http::assertSent(function (Request $request) use ($orderId) {
            if ($request->url() !== self::API.'/v1/payments') {
                return false;
            }
            $expires = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.vP', $request['date_of_expiration']);

            return $request['payment_method_id'] === 'pix'
                && $request['transaction_amount'] == 65.0
                && $request['external_reference'] === $orderId
                && $request['notification_url'] === 'https://exemplo.ngrok.test/api/v1/webhooks/mercadopago'
                && $request['payer'] === ['email' => 'maria@exemplo.com', 'first_name' => 'Maria', 'last_name' => 'Souza Lima']
                && $request->hasHeader('X-Idempotency-Key', "pix-{$orderId}")
                && $request->hasHeader('Authorization', 'Bearer TEST-token-de-teste')
                && $expires !== false
                && abs($expires->getTimestamp() - (time() + 30 * 60)) <= 1
                && ! array_key_exists('identification', $request['payer']); // full CPF is never stored nor sent
        });
    }

    public function test_expiration_is_never_below_mercado_pago_minimum(): void
    {
        config(['commerce.payments.mercadopago.pix_expiration_minutes' => 5]);
        $this->freezeSecond();

        $this->newPixOrder()->assertCreated();

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/v1/payments'
            && abs(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.vP', $r['date_of_expiration'])->getTimestamp() - (time() + 30 * 60)) <= 1);
    }

    public function test_paid_pix_credits_once_through_the_mercado_pago_webhook(): void
    {
        $order = Order::query()->findOrFail($this->newPixOrder('premium')->json('data.id'));
        $this->paymentFor($order, 'approved');

        $this->notify()->assertOk()->assertJsonPath('result', 'aplicado');
        $this->notify()->assertOk()->assertJsonPath('result', 'duplicado');

        $this->assertSame(50, $this->balance());
        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame('pago', $order->refresh()->status->value);

        $outbox = OutboxMessage::query()->sole();
        $this->assertSame('commerce.order.paid', $outbox->type);
        $this->assertSame('pix', $outbox->payload['payment_method']);

        $this->getJson("/api/v1/orders/{$order->id}", $this->asUser($this->userId))
            ->assertOk()->assertJsonPath('data.status', 'pago');
    }

    public function test_waiting_transfer_keeps_the_order_pending(): void
    {
        $order = Order::query()->findOrFail($this->newPixOrder()->json('data.id'));
        $this->paymentFor($order, 'pending');

        $this->notify()->assertOk()->assertJsonPath('result', 'ignorado');

        $this->assertSame('pendente', $order->refresh()->status->value);
        $this->assertSame(0, $this->balance());
    }

    public function test_expired_qr_code_fails_the_order(): void
    {
        $order = Order::query()->findOrFail($this->newPixOrder()->json('data.id'));
        $this->paymentFor($order, 'cancelled');

        $this->notify()->assertOk()->assertJsonPath('result', 'aplicado');

        $this->assertSame('falhou', $order->refresh()->status->value);
        $this->assertSame(0, $this->balance());
        $this->assertSame('commerce.order.failed', OutboxMessage::query()->sole()->type);
    }

    public function test_amount_different_from_the_order_never_credits(): void
    {
        $order = Order::query()->findOrFail($this->newPixOrder()->json('data.id'));
        $this->paymentFor($order, 'approved', amount: 0.01);

        $this->notify()->assertOk()->assertJsonPath('result', 'ignorado');

        $this->assertSame('pendente', $order->refresh()->status->value);
        $this->assertSame(0, $this->balance());
    }

    public function test_provider_error_returns_503_and_marks_order_failed(): void
    {
        $this->createHttpStatus = 400;

        $this->newPixOrder()->assertStatus(503);

        $this->assertSame('falhou', Order::query()->sole()->status->value);
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_pix_unavailable_without_mercado_pago_or_when_disabled(): void
    {
        config(['commerce.payments.mercadopago.pix_enabled' => false]);
        $this->newPixOrder()->assertUnprocessable()
            ->assertJsonPath('errors.payment_method.0', 'Forma de pagamento ainda não disponível.');

        config(['commerce.payments.mercadopago.pix_enabled' => true, 'commerce.payments.mercadopago.access_token' => null]);
        $this->newPixOrder()->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
        Http::assertNotSent(fn (Request $r) => str_starts_with($r->url(), self::API));
    }

    public function test_simulator_cannot_settle_a_pix_order(): void
    {
        config(['commerce.payments.simulator.enabled' => true]);
        $order = Order::query()->findOrFail($this->newPixOrder()->json('data.id'));

        $this->postJson("/api/v1/orders/{$order->id}/simulate", ['outcome' => 'aprovado'], $this->asUser($this->userId))
            ->assertConflict()->assertJsonPath('message', 'Este pedido não usa o simulador de pagamento.');

        $this->assertSame('pendente', $order->refresh()->status->value);
        $this->assertSame(0, $this->balance());
    }
}
