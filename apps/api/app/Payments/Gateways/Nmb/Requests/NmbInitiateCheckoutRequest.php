<?php

namespace App\Payments\Gateways\Nmb\Requests;

use App\Models\Payment;

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
                'returnUrl' => (string) config('services.nmb.return_url'),
                'merchant' => [
                    'name' => (string) config('services.nmb.merchant_name'),
                    'url' => (string) config('services.nmb.merchant_url'),
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
