<?php

namespace Tests\Feature;

use App\Models\Order;
use Database\Seeders\PackageSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private string $userId;

    private int $identityStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PackageSeeder::class);
        $this->userId = (string) Str::uuid();

        // Fake Identity GET /me: answers for whoever the forwarded token belongs to.
        Http::fake(['identity.test/api/v1/me' => function (Request $request) {
            if ($this->identityStatus !== 200) {
                return Http::response(['message' => 'erro'], $this->identityStatus);
            }
            $token = substr($request->header('Authorization')[0] ?? '', 7);
            $sub = json_decode(base64_decode(strtr(explode('.', $token)[1] ?? '', '-_', '+/')), true)['sub'] ?? null;

            return Http::response(['data' => [
                'id' => $sub, 'name' => 'Maria Souza', 'email' => 'maria@exemplo.com',
                'cpf' => '***.982.247-**', 'type' => 'comum', 'status' => 'ativo',
            ]]);
        }]);
    }

    private function createOrder(array $body = [], array $headers = [], ?string $userId = null)
    {
        return $this->postJson('/api/v1/orders', $body + ['package' => 'medio', 'payment_method' => 'simulador'],
            $headers + $this->asUser($userId ?? $this->userId));
    }

    // ---------------- catalog ----------------

    public function test_catalog_lists_the_three_packages_of_d2(): void
    {
        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'basico')->assertJsonPath('data.0.credits', 10)->assertJsonPath('data.0.price', 'R$ 30,00')
            ->assertJsonPath('data.1.code', 'medio')->assertJsonPath('data.1.credits', 25)->assertJsonPath('data.1.price', 'R$ 65,00')
            ->assertJsonPath('data.2.code', 'premium')->assertJsonPath('data.2.credits', 50)->assertJsonPath('data.2.price', 'R$ 120,00')
            ->assertJsonPath('data.2.price_per_credit', 'R$ 2,40');
    }

    public function test_capture_costs(): void
    {
        $this->getJson('/api/v1/credits/costs')->assertOk()->assertExactJson(['data' => ['imagem' => 1, 'video' => 2]]);
    }

    // ---------------- create order ----------------

    public function test_creates_pending_order_with_billing_from_identity(): void
    {
        $this->createOrder()
            ->assertCreated()
            ->assertJsonPath('data.status', 'pendente')
            ->assertJsonPath('data.package.code', 'medio')
            ->assertJsonPath('data.package.credits', 25)
            ->assertJsonPath('data.amount', 'R$ 65,00')
            ->assertJsonPath('data.user_id', $this->userId)
            ->assertJsonPath('data.billing.name', 'Maria Souza')
            ->assertJsonPath('data.billing.cpf', '***.982.247-**');

        // The user's own token was forwarded to Identity
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/me') && $r->hasHeader('Authorization'));
    }

    public function test_same_idempotency_key_returns_the_same_order(): void
    {
        $headers = ['Idempotency-Key' => 'compra-123-abc'];

        $first = $this->createOrder([], $headers)->assertCreated()->json('data.id');
        $this->createOrder([], $headers)->assertOk()->assertJsonPath('data.id', $first);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_idempotency_key_reused_with_other_package_is_409(): void
    {
        $headers = ['Idempotency-Key' => 'compra-123-abc'];
        $this->createOrder([], $headers)->assertCreated();

        $this->createOrder(['package' => 'premium'], $headers)->assertStatus(409);
    }

    public function test_same_key_for_different_users_creates_two_orders(): void
    {
        $other = (string) Str::uuid();
        $this->createOrder([], ['Idempotency-Key' => 'compra-123-abc'])->assertCreated();

        $this->createOrder([], ['Idempotency-Key' => 'compra-123-abc'], $other)->assertCreated();

        $this->assertSame(2, Order::query()->count());
    }

    public function test_invalid_package_or_method_is_422(): void
    {
        $this->createOrder(['package' => 'empresa'])->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->createOrder(['payment_method' => 'boleto'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->createOrder(['payment_method' => 'mercadopago'])->assertUnprocessable()
            ->assertJsonPath('errors.payment_method.0', 'Forma de pagamento ainda não disponível.');
    }

    public function test_simulator_disabled_is_not_available(): void
    {
        config(['commerce.payments.simulator.enabled' => false]);

        $this->createOrder()->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    public function test_identity_unavailable_is_503_and_no_order_is_created(): void
    {
        $this->identityStatus = 500;

        $this->createOrder()->assertStatus(503);
        $this->assertSame(0, Order::query()->count());
    }

    // ---------------- authentication (ADR-001) ----------------

    public function test_without_token_is_401(): void
    {
        $this->postJson('/api/v1/orders', ['package' => 'medio', 'payment_method' => 'simulador'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_token_signed_with_another_key_is_401(): void
    {
        $forged = $this->identityToken($this->userId, 'admin', [], self::generateRsaPair()['private']);

        $this->getJson('/api/v1/orders', $this->bearer($forged))->assertUnauthorized();
    }

    public function test_hs256_token_using_identity_public_key_as_secret_is_401(): void
    {
        $forged = JWT::encode(['sub' => $this->userId, 'type' => 'admin', 'exp' => time() + 900], self::identityKeys()['public'], 'HS256');

        $this->getJson('/api/v1/orders', $this->bearer($forged))->assertUnauthorized();
    }

    public function test_expired_token_is_401(): void
    {
        $expired = $this->identityToken($this->userId, 'comum', ['exp' => time() - 10]);

        $this->getJson('/api/v1/orders', $this->bearer($expired))->assertUnauthorized();
    }

    // ---------------- list / show ----------------

    public function test_user_lists_only_own_orders_and_admin_lists_all(): void
    {
        $this->createOrder()->assertCreated();
        $other = (string) Str::uuid();
        $this->createOrder([], [], $other)->assertCreated();

        $this->getJson('/api/v1/orders', $this->asUser($this->userId))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $this->userId);

        $this->getJson('/api/v1/orders?per_page=1', $this->asUser((string) Str::uuid(), 'admin'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
    }

    public function test_list_filters_by_status_and_validates(): void
    {
        $this->createOrder()->assertCreated();

        $this->getJson('/api/v1/orders?status=pago', $this->asUser($this->userId))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/orders?status=pendente', $this->asUser($this->userId))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/orders?per_page=51', $this->asUser($this->userId))->assertUnprocessable();
    }

    public function test_show_owner_admin_and_other_user(): void
    {
        $id = $this->createOrder()->json('data.id');

        $this->getJson("/api/v1/orders/{$id}", $this->asUser($this->userId))->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson("/api/v1/orders/{$id}", $this->asUser((string) Str::uuid(), 'admin'))->assertOk();
        $this->getJson("/api/v1/orders/{$id}", $this->asUser((string) Str::uuid()))->assertForbidden()
            ->assertExactJson(['message' => 'Acesso negado.']);
        $this->getJson('/api/v1/orders/'.Str::uuid(), $this->asUser($this->userId))->assertNotFound();
    }
}
