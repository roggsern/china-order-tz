<?php

namespace App\Services\Pricing\DTOs;

final class PriceQuoteInput
{
    public function __construct(
        public readonly string $productId,
        public readonly ?string $configurationId,
        public readonly int $quantity,
        public readonly ?string $promotionCode = null,
        public readonly ?string $couponCode = null,
        public readonly ?string $customerGroupId = null,
    ) {}
}
