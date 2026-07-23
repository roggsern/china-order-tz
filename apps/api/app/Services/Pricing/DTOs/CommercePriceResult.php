<?php

namespace App\Services\Pricing\DTOs;

use App\Enums\PurchasabilityPath;

/**
 * Catalog → Quote unit price result (ADR 054).
 * Not a Transaction Price — callers freeze onto cart/order separately.
 */
final class CommercePriceResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $resolved,
        public readonly string $unitPrice,
        public readonly string $currency,
        public readonly PurchasabilityPath $path,
        public readonly string $source,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function unresolved(
        string $currency,
        PurchasabilityPath $path,
        string $source,
        array $meta = [],
    ): self {
        return new self(
            resolved: false,
            unitPrice: '0.00',
            currency: strtoupper($currency),
            path: $path,
            source: $source,
            meta: $meta,
        );
    }
}
