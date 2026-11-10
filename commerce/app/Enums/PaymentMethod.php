<?php

namespace App\Enums;

/**
 * Payment providers (ADR-006). Each one is a gateway behind the Payments Adapter.
 */
enum PaymentMethod: string
{
    case Simulator = 'simulador';      // T06 "Simular confirmação / falha" — tests and demos
    case MercadoPago = 'mercadopago';  // Phase 6
    case Pix = 'pix';                  // Phase 7 (PSP to be defined)

    /** Methods that can be chosen right now in this environment. */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Simulator => (bool) config('commerce.payments.simulator.enabled'),
            self::MercadoPago => filled(config('commerce.payments.mercadopago.access_token'))
                && filled(config('commerce.payments.mercadopago.webhook_secret'))
                && filled(config('commerce.payments.mercadopago.notification_url')),
            self::Pix => false,
        };
    }

    /** @return list<string> */
    public static function availableValues(): array
    {
        return array_values(array_map(
            fn (self $method) => $method->value,
            array_filter(self::cases(), fn (self $method) => $method->isAvailable()),
        ));
    }
}
