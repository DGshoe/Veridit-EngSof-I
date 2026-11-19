<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookException;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Payments Adapter (ADR-006): every provider (simulator, Mercado Pago, PIX PSP)
 * implements this contract. The rest of Commerce never sees provider details.
 */
interface PaymentGateway
{
    public function method(): PaymentMethod;

    /** Opens the charge at the provider for a pending order. */
    public function createCharge(Order $order): Charge;

    /**
     * Authenticates (signature) and translates a webhook into a ProviderEvent.
     * Returns null for authentic notifications that are irrelevant (other topics,
     * test pings, unknown payments): they are acknowledged with 200 and ignored.
     *
     * @throws InvalidWebhookException signature missing/invalid (401)
     * @throws PaymentProviderUnavailableException provider API down (503: the provider retries)
     */
    public function parseWebhook(Request $request): ?ProviderEvent;
}
