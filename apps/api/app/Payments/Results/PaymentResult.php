<?php

namespace App\Payments\Results;

use App\Payments\ValueObjects\TransactionReference;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $message,
        public readonly ?TransactionReference $transactionReference = null,
    ) {}

    public function transactionReferenceValue(): ?string
    {
        return $this->transactionReference?->value();
    }
}
