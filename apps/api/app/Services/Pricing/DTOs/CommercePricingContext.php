<?php

namespace App\Services\Pricing\DTOs;

/**
 * Resolution context for CommercePricingResolver (ADR 054).
 *
 * Customer / channel / region / priceType are reserved for future stages.
 */
final class CommercePricingContext
{
    public function __construct(
        public readonly string $currency = 'TZS',
        public readonly int $quantity = 1,
        /**
         * When true, Variant path may use legacy product_variants.price / products.price
         * when no active retail variant_prices row exists (quote + cart parity).
         */
        public readonly bool $allowLegacyVariantFallback = true,
        public readonly ?string $customerId = null,
        public readonly ?string $customerGroupId = null,
        public readonly ?string $channel = null,
        public readonly ?string $region = null,
        public readonly ?string $priceType = null,
    ) {}

    public function currency(): string
    {
        return strtoupper($this->currency);
    }
}
