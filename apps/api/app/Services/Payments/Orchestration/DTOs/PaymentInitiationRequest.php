<?php

namespace App\Services\Payments\Orchestration\DTOs;

use App\Models\Order;

final class PaymentInitiationRequest
{
    public function __construct(
        public readonly Order $order,
        public readonly string $merchantReference,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $provider,
    ) {}
}
