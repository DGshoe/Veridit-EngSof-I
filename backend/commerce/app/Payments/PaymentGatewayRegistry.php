<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Payments\Gateways\MercadoPagoGateway;
use App\Payments\Gateways\MercadoPagoPixGateway;
use App\Payments\Gateways\SimulatorGateway;
use Illuminate\Contracts\Container\Container;

/** Picks the gateway (adapter) for a payment method. */
class PaymentGatewayRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(PaymentMethod $method): PaymentGateway
    {
        return match ($method) {
            PaymentMethod::Simulator => $this->container->make(SimulatorGateway::class),
            PaymentMethod::MercadoPago => $this->container->make(MercadoPagoGateway::class),
            PaymentMethod::Pix => $this->container->make(MercadoPagoPixGateway::class),
        };
    }
}
