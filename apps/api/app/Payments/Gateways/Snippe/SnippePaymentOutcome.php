<?php

namespace App\Payments\Gateways\Snippe;

use App\Enums\PaymentTransactionStatus;

final class SnippePaymentOutcome
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly bool $ok,
        public readonly PaymentTransactionStatus $status,
        public readonly string $message,
        public readonly array $context = [],
    ) {}
}
