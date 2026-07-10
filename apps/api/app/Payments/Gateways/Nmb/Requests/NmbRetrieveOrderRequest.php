<?php

namespace App\Payments\Gateways\Nmb\Requests;

use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class NmbRetrieveOrderRequest
{
    public function __construct(
        private readonly string $orderId,
    ) {}

    public static function fromPayment(Payment $payment): self
    {
        $orderId = filled($payment->reference) ? (string) $payment->reference : null;

        if ($orderId === null) {
            throw ValidationException::withMessages([
                'payment' => ['Payment reference is required for NMB verification.'],
            ]);
        }

        return new self($orderId);
    }

    public function orderId(): string
    {
        return $this->orderId;
    }
}
