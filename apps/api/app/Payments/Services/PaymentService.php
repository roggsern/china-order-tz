<?php

namespace App\Payments\Services;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\Exceptions\PaymentGatewayNotFoundException;
use App\Payments\Gateways\MockPaymentGateway;
use App\Payments\Gateways\NmbPaymentGateway;
use Illuminate\Contracts\Container\Container;

class PaymentService
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function gatewayFor(Payment $payment): PaymentGatewayInterface
    {
        $gatewayClass = match ($payment->method) {
            PaymentMethod::Nmb, PaymentMethod::BankTransfer => NmbPaymentGateway::class,
            default => $this->defaultGatewayClass(),
        };

        $gateway = $this->container->make($gatewayClass);

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw PaymentGatewayNotFoundException::forMethod($payment->method);
        }

        return $gateway;
    }

    private function defaultGatewayClass(): string
    {
        return match (config('payments.default_gateway')) {
            'nmb' => NmbPaymentGateway::class,
            'mock' => MockPaymentGateway::class,
            default => MockPaymentGateway::class,
        };
    }
}
