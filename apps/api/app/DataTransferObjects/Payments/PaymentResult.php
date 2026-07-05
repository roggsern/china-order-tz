<?php

namespace App\DataTransferObjects\Payments;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $transactionReference = null,
    ) {}
}
