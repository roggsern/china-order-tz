<?php

namespace App\Payments\DTOs;

class InitiatePaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $checkoutRequestId = null,
    ) {}
}
