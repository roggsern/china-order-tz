<?php

namespace App\Payments\Gateways\Nmb;

readonly class NmbPaymentCompletionResult
{
    public function __construct(
        public bool $completed,
        public bool $alreadyCompleted,
        public string $message,
        public ?string $paymentId = null,
        public ?string $orderId = null,
    ) {}
}
