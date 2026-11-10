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
 * ADR-006 / REQ06 — Mercado Pago Checkout Pro with the HTTP API faked.
 * (The real sandbox run needs the team's test credentials.)
 */
class MercadoPagoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'mp-webhook-secret-de-teste';

    private const API = 'https://api.mercadopago.test';

    private string $userId;

    /** Payment returned by GET /v1/payments/{id}; null = 404 */
    private ?array $payment = null;

    private int $paymentHttpStatus = 200;

    private int $preferenceHttpStatus = 201;

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
            'back_url' => 'http://localhost:5173/confirmacao',
            'api_url' => self::API,
            'timeout' => 5,
        ]]);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'identity.test')) {
                return Http::response(['data' => ['id' => $this->userId, 'name' => 'Maria Souza', 'email' => 'maria@exemplo.com', 'cpf' => '***.982.247-**']]);
            }
            if ($request->method() === 'POST' && $url === self::API.'/checkout/preferences') {
                return Http::response($this->preferenceHttpStatus === 201 ? [
                    'id' => '123456-pref-'.Str::random(8),
                    'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=abc',
                    'sandbox_init_point' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=abc',
                ] : ['message' => 'erro'], $this->preferenceHttpStatus);
            }
            if ($request->method() === 'GET' && str_starts_with($url, self::API.'/v1/payments/')) {
                if ($this->paymentHttpStatus !== 200) {
                    return Http::response(['message' => 'erro'], $this->paymentHttpStatus);
                }

                return $this->payment ? Http::response($this->payment) : Http::response(['message' => 'not found'], 404);
            }

            return Http::response('rota inesperada no fake', 599);
        });
    }

    private function newOrder(string $package = 'medio'): Order
    {
        $id = $this->postJson('/api/v1/orders', ['package' => $package, 'payment_method' => 'mercadopago'], $this->asUser($this->userId))
            ->assertCreated()->json('data.id');

        return Order::query()->findOrFail($id);
    }

    private function paymentFor(Order $order, string $status, ?float $amount = null, string $paymentId = '987654321'): void
    {
        $this->payment = [
            'id' => (int) $paymentId,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'cc_rejected_other_reason',
            'external_reference' => $order->id,
            'transaction_amount' => $amount ?? $order->amount_cents / 100,
            'currency_id' => 'BRL',
            'payment_method_id' => 'pix',
            'payer' => ['email' => 'comprador@teste.com'], // never stored
        ];
    }

    /** Sends a notification signed exactly as Mercado Pago does. */
    private function notify(string $paymentId = '987654321', string $type = 'payment', ?string $signature = null, string $requestId = 'req-abc-123')
    {
        $ts = (string) time();
        $manifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
        $signature ??= 'ts='.$ts.',v1='.hash_hmac('sha256', $manifest, self::SECRET);

        return $this->call('POST', "/api/v1/webhooks/mercadopago?data.id={$paymentId}&type={$type}", server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_REQUEST_ID' => $requestId,
        ], content: json_encode(['action' => 'payment.updated', 'type' => $type, 'data' => ['id' => $paymentId]]));
    }

    private function balance(): int
    {
        return $this->getJson('/api/v1/wallet', $this->asUser($this->userId))->json('data.balance');
    }

    // ---------------- charge (preference) ----------------

    public function test_order_creates_a_checkout_pro_preference(): void
    {
        $order = $this->newOrder('medio');

        $this->assertSame('https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=abc', $order->payment_data['checkout_url']);
        $this->assertStringStartsWith('123456-pref-', $order->provider_payment_id);

        Http::assertSent(function (Request $request) use ($order) {
            if ($request->url() !== self::API.'/checkout/preferences') {
                return false;
            }
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer TEST-token-de-teste')
                && $request->hasHeader('X-Idempotency-Key', $order->id)
                && $body['external_reference'] === $order->id
                && $body['items'][0]['unit_price'] == 65.0
                && $body['items'][0]['currency_id'] === 'BRL'
                && $body['payer']['email'] === 'maria@exemplo.com'
                && $body['notification_url'] === 'https://exemplo.ngrok.test/api/v1/webhooks/mercadopago'
                && str_contains($body['back_urls']['success'], $order->id)
                && ! isset($body['auto_return']); // http back_url: MP requires https for auto_return
        });
    }

    public function test_mercadopago_unavailable_when_not_configured(): void
    {
        config(['commerce.payments.mercadopago.access_token' => null]);

        $this->postJson('/api/v1/orders', ['package' => 'medio', 'payment_method' => 'mercadopago'], $this->asUser($this->userId))
            ->assertUnprocessable()->assertJsonPath('errors.payment_method.0', 'Forma de pagamento ainda não disponível.');
    }

    public function test_preference_error_returns_503_and_marks_order_failed(): void
    {
        $this->preferenceHttpStatus = 500;

        $this->postJson('/api/v1/orders', ['package' => 'medio', 'payment_method' => 'mercadopago'], $this->asUser($this->userId))
            ->assertStatus(503);

        $this->assertSame('falhou', Order::query()->sole()->status->value);
    }

    // ---------------- webhook ----------------

    public function test_approved_payment_credits_once_even_if_notified_again(): void
    {
        $order = $this->newOrder('premium');
        $this->paymentFor($order, 'approved');

        $this->notify()->assertOk()->assertJsonPath('result', 'aplicado');
        $this->notify()->assertOk()->assertJsonPath('result', 'duplicado');
        $this->notify()->assertOk()->assertJsonPath('result', 'duplicado');

        $this->assertSame(50, $this->balance());
        $this->assertSame(1, LedgerEntry::query()->count());
        $order->refresh();
        $this->assertSame('pago', $order->status->value);
        $this->assertSame('987654321', $order->payment_data['paid_payment_id']);
        $this->assertSame('commerce.order.paid', OutboxMessage::query()->sole()->type);

        // Status read from the API with our token
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/v1/payments/987654321' && $r->hasHeader('Authorization', 'Bearer TEST-token-de-teste'));
    }

    public function test_pending_then_approved(): void
    {
        $order = $this->newOrder('basico');

        $this->paymentFor($order, 'pending');
        $this->notify()->assertOk()->assertJsonPath('result', 'ignorado');
        $this->assertSame('pendente', $order->refresh()->status->value);

        $this->paymentFor($order, 'approved');
        $this->notify()->assertOk()->assertJsonPath('result', 'aplicado');
        $this->assertSame(10, $this->balance());
    }

    public function test_rejected_payment_fails_the_order(): void
    {
        $order = $this->newOrder();
        $this->paymentFor($order, 'rejected');

        $this->notify()->assertOk()->assertJsonPath('result', 'aplicado');

        $this->assertSame('falhou', $order->refresh()->status->value);
        $this->assertSame(0, $this->balance());
    }

    public function test_amount_different_from_the_order_never_credits(): void
    {
        $order = $this->newOrder('premium');
        $this->paymentFor($order, 'approved', 1.00);

        $this->notify()->assertOk()->assertJsonPath('result', 'ignorado');

        $this->assertSame('pendente', $order->refresh()->status->value);
        $this->assertSame(0, $this->balance());
    }

    public function test_payment_of_another_method_order_or_bad_reference_is_ignored(): void
    {
        $order = $this->newOrder();
        $this->paymentFor($order, 'approved');
        $this->payment['external_reference'] = 'nao-e-uuid';

        $this->notify()->assertOk()->assertJsonPath('result', 'ignorado');
        $this->assertSame(0, $this->balance());
    }

    public function test_invalid_or_missing_signature_is_401(): void
    {
        $order = $this->newOrder();
        $this->paymentFor($order, 'approved');

        $this->notify(signature: 'ts=1,v1='.str_repeat('a', 64))->assertUnauthorized()->assertExactJson(['message' => 'Notificação inválida.']);
        $this->notify(signature: '')->assertUnauthorized();

        // Signed for another payment id
        $ts = (string) time();
        $forOther = 'ts='.$ts.',v1='.hash_hmac('sha256', "id:111;request-id:req-abc-123;ts:{$ts};", self::SECRET);
        $this->notify('987654321', signature: $forOther)->assertUnauthorized();

        $this->assertSame(0, $this->balance());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/payments/'));
    }

    public function test_other_topics_are_acknowledged_without_calling_the_api(): void
    {
        $this->notify('555', 'merchant_order')->assertOk()->assertJsonPath('result', 'ignorado');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/payments/'));
    }

    public function test_unknown_payment_is_ignored(): void
    {
        $this->payment = null; // API answers 404 (e.g. panel test notification)

        $this->notify('123456')->assertOk()->assertJsonPath('result', 'ignorado');
    }

    public function test_api_down_returns_503_so_mercadopago_retries(): void
    {
        $order = $this->newOrder();
        $this->paymentFor($order, 'approved');
        $this->paymentHttpStatus = 500;

        $this->notify()->assertStatus(503);
        $this->assertSame('pendente', $order->refresh()->status->value);

        // Retry after recovery is processed normally (nothing was stored in the inbox)
        $this->paymentHttpStatus = 200;
        $this->notify()->assertOk()->assertJsonPath('result', 'aplicado');
        $this->assertSame(25, $this->balance());
    }
}
