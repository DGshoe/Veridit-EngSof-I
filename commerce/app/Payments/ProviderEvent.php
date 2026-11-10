<?php

namespace App\Payments;

use App\Enums\PaymentMethod;

/** A payment notification already validated and translated by a gateway. */
final readonly class ProviderEvent
{
    public function __construct(
        public PaymentMethod $provider,
        public string $eventId,
        public string $providerPaymentId,
        public PaymentStatus $status,
        public array $payload = [],
    ) {}
}
