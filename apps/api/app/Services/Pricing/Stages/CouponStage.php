<?php

namespace App\Services\Pricing\Stages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;

/**
 * Reserved stage — implemented in Phase E. Pass-through only.
 */
final class CouponStage implements PriceStageInterface
{
    public function key(): string
    {
        return 'coupon';
    }

    public function label(): string
    {
        return 'Coupon';
    }

    public function apply(
        Product $product,
        ?ProductVariant $configuration,
        PriceQuoteInput $input,
        string $currentUnitPrice,
    ): PriceStageResult {
        return new PriceStageResult(
            stage: $this->key(),
            label: $this->label(),
            unitPrice: $currentUnitPrice,
            applied: false,
            note: 'Reserved — coupons not implemented yet',
            meta: [
                'coupon_code' => $input->couponCode,
                'reserved' => true,
            ],
        );
    }
}
