<?php

namespace App\Services;

use App\Auth\IdentityUser;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Models\Order;
use App\Models\Package;
use App\Payments\PaymentGatewayRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/** Packages module: purchase of a package (REQ05). */
class OrderService
{
    public function __construct(
        private readonly IdentityClient $identity,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    /**
     * Creates a pending order. Credits are granted only when the payment
     * provider confirms the payment (never here).
     *
     * With an Idempotency-Key, repeating the request returns the same order
     * instead of creating a second purchase.
     *
     * @return array{0: Order, 1: bool} [order, created now?]
     */
    public function create(IdentityUser $user, Package $package, PaymentMethod $method, ?string $idempotencyKey): array
    {
        if ($idempotencyKey !== null && ($existing = $this->findReplay($user, $idempotencyKey, $package, $method))) {
            return [$existing, false];
        }

        $billing = $this->identity->billingDataFor($user);

        try {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'package_code' => $package->code,
                'credits' => $package->credits,
                'amount_cents' => $package->price_cents,
                'payment_method' => $method,
                'idempotency_key' => $idempotencyKey,
                'billing_name' => $billing['name'],
                'billing_email' => $billing['email'],
                'billing_cpf_masked' => $billing['cpf_masked'],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced: the other one won, return its order.
            if ($idempotencyKey !== null && ($existing = $this->findReplay($user, $idempotencyKey, $package, $method))) {
                return [$existing, false];
            }
            throw $e;
        }

        $this->openCharge($order);

        return [$order, true];
    }

    /** Opens the charge at the provider (outside any DB transaction: it is an external call). */
    private function openCharge(Order $order): void
    {
        try {
            $charge = $this->gateways->for($order->payment_method)->createCharge($order);
        } catch (Throwable $e) {
            $order->update(['status' => OrderStatus::Failed, 'failed_at' => now()]);

            // Gateways only put the provider's error code/description in the message (no tokens, no personal data)
            throw new PaymentProviderUnavailableException(
                $order->payment_method->value,
                $e::class.': '.mb_strimwidth($e->getMessage(), 0, 300, '...'),
            );
        }

        $order->update([
            'provider_payment_id' => $charge->providerPaymentId,
            'payment_data' => $charge->data,
        ]);
    }

    private function findReplay(IdentityUser $user, string $key, Package $package, PaymentMethod $method): ?Order
    {
        $existing = Order::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing && ($existing->package_id !== $package->id || $existing->payment_method !== $method)) {
            throw new IdempotencyConflictException;
        }

        return $existing;
    }
}
