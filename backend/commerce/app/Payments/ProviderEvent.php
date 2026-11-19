<?php

namespace App\Payments;

use App\Enums\PaymentMethod;

/** A payment notification already validated and translated by a gateway. */
final readonly class ProviderEvent
{
    /**
     * @param  ?string  $orderId  Our order id, when the provider echoes it (e.g. Mercado Pago external_reference).
     *                            Without it, the order is found by provider_payment_id.
     * @param  ?int  $amountCents  Amount actually paid, when the provider reports it (checked against the order).
     */
    public function __construct(
        public PaymentMethod $provider,
        public string $eventId,
        public string $providerPaymentId,
        public PaymentStatus $status,
        public array $payload = [],
        public ?string $orderId = null,
        public ?int $amountCents = null,
    ) {}
}
