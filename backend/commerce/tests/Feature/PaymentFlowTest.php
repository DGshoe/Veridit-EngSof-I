<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\PaymentEvent;
use App\Payments\Gateways\SimulatorGateway;
use App\Payments\PaymentStatus;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * REQ05/06 + ADR-002/011: confirmation credits exactly once, failures never
 * credit, final orders never change, and the outbox is written with the effect.
 */
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PackageSeeder::class);
        $this->userId = (string) Str::uuid();

        Http::fake(['identity.test/api/v1/me' => Http::response(['data' => [
            'id' => $this->userId, 'name' => 'Maria Souza', 'email' => 'maria@exemplo.com', 'cpf' => '***.982.247-**',
        ]])]);
    }

    private function newOrder(string $package = 'medio'): Order
    {
        $id = $this->postJson('/api/v1/orders', ['package' => $package, 'payment_method' => 'simulador'], $this->asUser($this->userId))
            ->assertCreated()
            ->assertJsonPath('data.payment.method', 'simulador')
            ->json('data.id');

        return Order::query()->findOrFail($id);
    }

    private function simulate(Order $order, string $outcome, ?string $userId = null)
    {
        return $this->postJson("/api/v1/orders/{$order->id}/simulate", ['outcome' => $outcome], $this->asUser($userId ?? $this->userId));
    }

    private function sendWebhook(string $paymentId, PaymentStatus $status, string $eventId, ?string $signature = null)
    {
        $notification = app(SimulatorGateway::class)->notification($paymentId, $status, $eventId);

        return $this->call('POST', '/api/v1/webhooks/simulator', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIMULATOR_SIGNATURE' => $signature ?? $notification['signature'],
        ], content: $notification['body']);
    }

    private function balance(): int
    {
        return $this->getJson('/api/v1/wallet', $this->asUser($this->userId))->json('data.balance');
    }

    // ---------------- happy path ----------------

    public function test_order_opens_a_charge_at_the_provider(): void
    {
        $order = $this->newOrder();

        $this->assertStringStartsWith('sim_', $order->provider_payment_id);
        $this->assertNotEmpty($order->payment_data['instructions']);
        $this->assertSame(0, $this->balance()); // no credit before confirmation
    }

    public function test_approved_simulation_pays_credits_and_records_outbox(): void
    {
        $order = $this->newOrder('medio');

        $this->simulate($order, 'aprovado')
            ->assertOk()
            ->assertJsonPath('data.status', 'pago')
            ->assertJsonPath('message', 'Pagamento confirmado. Créditos adicionados.');

        $this->assertSame(25, $this->balance());
        $this->assertNotNull($order->refresh()->paid_at);

        $event = OutboxMessage::query()->sole();
        $this->assertSame('commerce.order.paid', $event->type);
        $this->assertSame($order->id, $event->payload['order_id']);
        $this->assertSame(25, $event->payload['credits']);
        $this->assertSame(25, $event->payload['balance']);
        $this->assertSame('maria@exemplo.com', $event->payload['billing_email']);
        $this->assertArrayNotHasKey('cpf', $event->payload);
        $this->assertNull($event->published_at);
    }

    public function test_rejected_simulation_fails_without_credit(): void
    {
        $order = $this->newOrder();

        $this->simulate($order, 'recusado')->assertOk()->assertJsonPath('data.status', 'falhou');

        $this->assertSame(0, $this->balance());
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame('commerce.order.failed', OutboxMessage::query()->sole()->type);
    }

    // ---------------- idempotency (ADR-002/011) ----------------

    public function test_same_webhook_delivered_three_times_credits_once(): void
    {
        $order = $this->newOrder('premium');

        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1')->assertOk()->assertJsonPath('result', 'aplicado');
        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1')->assertOk()->assertJsonPath('result', 'duplicado');
        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1')->assertOk()->assertJsonPath('result', 'duplicado');

        $this->assertSame(50, $this->balance());
        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_different_approval_events_for_the_same_payment_credit_once(): void
    {
        $order = $this->newOrder('basico');

        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1')->assertJsonPath('result', 'aplicado');
        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-2')->assertJsonPath('result', 'ignorado');

        $this->assertSame(10, $this->balance());
        $this->assertSame(2, PaymentEvent::query()->count()); // both kept for audit
    }

    public function test_paid_order_never_goes_back(): void
    {
        $order = $this->newOrder();
        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1');

        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Rejected, 'evt-2')->assertOk()->assertJsonPath('result', 'ignorado');

        $this->assertSame('pago', $order->refresh()->status->value);
        $this->assertSame(25, $this->balance());
    }

    public function test_pending_event_changes_nothing(): void
    {
        $order = $this->newOrder();

        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Pending, 'evt-1')->assertOk()->assertJsonPath('result', 'ignorado');

        $this->assertSame('pendente', $order->refresh()->status->value);
    }

    public function test_unknown_payment_is_accepted_and_ignored(): void
    {
        $this->sendWebhook('sim_desconhecido', PaymentStatus::Approved, 'evt-x')->assertOk()->assertJsonPath('result', 'ignorado');

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    // ---------------- security ----------------

    public function test_webhook_with_invalid_signature_is_401_and_does_nothing(): void
    {
        $order = $this->newOrder();

        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1', str_repeat('0', 64))
            ->assertUnauthorized()->assertExactJson(['message' => 'Notificação inválida.']);

        $this->postJson('/api/v1/webhooks/simulator', ['event_id' => 'e', 'payment_id' => $order->provider_payment_id, 'status' => 'aprovado'])
            ->assertUnauthorized();

        $this->assertSame('pendente', $order->refresh()->status->value);
        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_only_the_owner_can_simulate(): void
    {
        $order = $this->newOrder();

        $this->simulate($order, 'aprovado', (string) Str::uuid())->assertForbidden();
        $this->postJson("/api/v1/orders/{$order->id}/simulate", ['outcome' => 'aprovado'], $this->asUser((string) Str::uuid(), 'admin'))
            ->assertForbidden();
        $this->postJson("/api/v1/orders/{$order->id}/simulate", ['outcome' => 'aprovado'])->assertUnauthorized();
    }

    public function test_simulating_a_final_order_is_409(): void
    {
        $order = $this->newOrder();
        $this->simulate($order, 'aprovado')->assertOk();

        $this->simulate($order, 'aprovado')->assertStatus(409);
        $this->assertSame(25, $this->balance());
    }

    public function test_simulate_validates_outcome(): void
    {
        $this->simulate($this->newOrder(), 'talvez')->assertUnprocessable()->assertJsonValidationErrors('outcome');
    }

    public function test_simulator_disabled_hides_simulate_and_webhook(): void
    {
        $order = $this->newOrder();
        config(['commerce.payments.simulator.enabled' => false]);

        $this->simulate($order, 'aprovado')->assertNotFound();
        $this->sendWebhook($order->provider_payment_id, PaymentStatus::Approved, 'evt-1')->assertNotFound();
    }

    // ---------------- full flow ----------------

    public function test_buy_pay_and_consume_end_to_end(): void
    {
        $this->simulate($this->newOrder('basico'), 'aprovado')->assertOk();

        $this->postJson('/api/v1/internal/credits/consume', [
            'user_id' => $this->userId, 'capture_id' => 'cap-1', 'capture_type' => 'video',
        ], ['X-Service-Token' => 'testing-only-service-token'])->assertCreated()->assertJsonPath('data.balance', 8);

        $this->getJson('/api/v1/wallet/entries', $this->asUser($this->userId))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reason', 'captura_video')
            ->assertJsonPath('data.1.reason', 'compra');
    }
}
