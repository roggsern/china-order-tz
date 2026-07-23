<?php

namespace App\Services\ProductPurchasability;

use App\Enums\PurchasabilityPath;

/**
 * Immutable evaluation result for Product purchasability / visibility (ADR 053).
 */
final class ProductPurchasabilityResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly PurchasabilityPath $path,
        public readonly bool $isPurchasable,
        public readonly bool $isVisible,
        public readonly array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
