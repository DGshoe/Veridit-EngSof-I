<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookException;
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
     *
     * @throws InvalidWebhookException
     */
    public function parseWebhook(Request $request): ProviderEvent;
}
