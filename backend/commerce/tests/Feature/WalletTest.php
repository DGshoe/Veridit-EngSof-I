<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientCreditsException;
use App\Models\LedgerEntry;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private const SERVICE = ['X-Service-Token' => 'testing-only-service-token'];

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (string) Str::uuid();
    }

    private function wallet(): WalletService
    {
        return app(WalletService::class);
    }

    private function consume(string $captureId, string $type = 'imagem', array $headers = self::SERVICE)
    {
        return $this->postJson('/api/v1/internal/credits/consume', [
            'user_id' => $this->userId, 'capture_id' => $captureId, 'capture_type' => $type,
        ], $headers);
    }

    // ---------------- service (ledger) ----------------

    public function test_new_user_has_zero_balance(): void
    {
        $this->getJson('/api/v1/wallet', $this->asUser($this->userId))
            ->assertOk()->assertExactJson(['data' => ['user_id' => $this->userId, 'balance' => 0]]);
    }

    public function test_credit_for_the_same_order_is_booked_once(): void
    {
        [, $first] = $this->wallet()->credit($this->userId, 25, 'compra', 'order', 'pedido-1');
        [, $again] = $this->wallet()->credit($this->userId, 25, 'compra', 'order', 'pedido-1');

        $this->assertTrue($first);
        $this->assertFalse($again);
        $this->assertSame(25, $this->wallet()->balanceOf($this->userId));
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_ledger_entries_are_immutable(): void
    {
        [$entry] = $this->wallet()->credit($this->userId, 10, 'compra', 'order', 'pedido-1');

        $this->expectException(LogicException::class);
        $entry->update(['amount' => 1000]);
    }

    public function test_balance_never_goes_negative(): void
    {
        $this->wallet()->credit($this->userId, 1, 'compra', 'order', 'pedido-1');

        try {
            $this->wallet()->debit($this->userId, 2, 'captura_video', 'capture', 'cap-1');
            $this->fail('Era esperado saldo insuficiente.');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(2, $e->required);
            $this->assertSame(1, $e->balance);
        }

        $this->assertSame(1, $this->wallet()->balanceOf($this->userId));
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    // ---------------- GET /wallet, /wallet/entries ----------------

    public function test_balance_and_statement_of_the_logged_user_only(): void
    {
        $this->wallet()->credit($this->userId, 25, 'compra', 'order', 'pedido-1');
        $this->wallet()->debit($this->userId, 1, 'captura_imagem', 'capture', 'cap-1');
        $this->wallet()->credit((string) Str::uuid(), 50, 'compra', 'order', 'pedido-de-outro');

        $this->getJson('/api/v1/wallet', $this->asUser($this->userId))->assertOk()->assertJsonPath('data.balance', 24);

        $this->getJson('/api/v1/wallet/entries', $this->asUser($this->userId))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.type', 'debito')->assertJsonPath('data.0.balance_after', 24)
            ->assertJsonPath('data.1.type', 'credito')->assertJsonPath('data.1.amount', 25);

        $this->getJson('/api/v1/wallet/entries?type=credito', $this->asUser($this->userId))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_wallet_without_token_is_401(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }

    // ---------------- contract with Evidence ----------------

    public function test_image_costs_1_and_video_costs_2(): void
    {
        $this->wallet()->credit($this->userId, 10, 'compra', 'order', 'pedido-1');

        $this->consume('cap-img', 'imagem')->assertCreated()
            ->assertJsonPath('data.entry.amount', 1)->assertJsonPath('data.entry.reason', 'captura_imagem')->assertJsonPath('data.balance', 9);
        $this->consume('cap-vid', 'video')->assertCreated()
            ->assertJsonPath('data.entry.amount', 2)->assertJsonPath('data.entry.reason', 'captura_video')->assertJsonPath('data.balance', 7);
    }

    public function test_repeated_consume_for_the_same_capture_debits_once(): void
    {
        $this->wallet()->credit($this->userId, 10, 'compra', 'order', 'pedido-1');

        $entryId = $this->consume('cap-1', 'video')->assertCreated()->json('data.entry.id');
        $this->consume('cap-1', 'video')->assertOk()->assertJsonPath('data.entry.id', $entryId)->assertJsonPath('data.balance', 8);

        $this->assertSame(8, $this->wallet()->balanceOf($this->userId));
    }

    public function test_same_capture_with_another_type_is_409(): void
    {
        $this->wallet()->credit($this->userId, 10, 'compra', 'order', 'pedido-1');
        $this->consume('cap-1', 'imagem')->assertCreated();

        $this->consume('cap-1', 'video')->assertStatus(409);
        $this->assertSame(9, $this->wallet()->balanceOf($this->userId));
    }

    public function test_insufficient_balance_is_422(): void
    {
        $this->wallet()->credit($this->userId, 1, 'compra', 'order', 'pedido-1');

        $this->consume('cap-1', 'video')->assertUnprocessable()
            ->assertJsonPath('message', 'Saldo insuficiente.')
            ->assertJsonValidationErrors('balance');
        $this->assertSame(1, $this->wallet()->balanceOf($this->userId));
    }

    public function test_consume_validates_input(): void
    {
        $this->postJson('/api/v1/internal/credits/consume', ['user_id' => 'x', 'capture_type' => 'audio'], self::SERVICE)
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'capture_id', 'capture_type']);
    }

    public function test_consume_requires_the_service_token(): void
    {
        $this->consume('cap-1', 'imagem', [])->assertUnauthorized();
        $this->consume('cap-1', 'imagem', ['X-Service-Token' => 'errado'])->assertUnauthorized();

        // A user JWT is not enough
        $this->consume('cap-1', 'imagem', $this->asUser($this->userId))->assertUnauthorized();
    }
}
