<?php

namespace App\Payments\Gateways\Nmb\Requests;

use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbConfig;

class NmbInitiateCheckoutRequest
{
    public function __construct(
        private readonly Payment $payment,
    ) {}

    public static function fromPayment(Payment $payment): self
    {
        return new self($payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'interaction' => [
                'operation' => 'PURCHASE',
                'returnUrl' => (string) NmbConfig::get('return_url'),
                'merchant' => [
                    'name' => (string) NmbConfig::get('merchant_name'),
                    'url' => (string) NmbConfig::get('merchant_url'),
                ],
            ],
            'order' => [
                'id' => (string) ($this->payment->reference ?? $this->payment->id),
                'amount' => number_format((float) $this->payment->amount, 2, '.', ''),
                'currency' => (string) $this->payment->currency,
                'description' => 'China Order TZ payment '.($this->payment->reference ?? $this->payment->id),
            ],
        ];
    }
}
