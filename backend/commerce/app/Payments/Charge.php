<?php

namespace App\Payments;

/** Result of opening a charge at the provider (payment id + data for the front, e.g. PIX QR code). */
final readonly class Charge
{
    public function __construct(
        public string $providerPaymentId,
        public array $data = [],
    ) {}
}
