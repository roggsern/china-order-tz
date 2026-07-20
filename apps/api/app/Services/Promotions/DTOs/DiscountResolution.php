<?php

namespace App\Services\Promotions\DTOs;

use App\Models\Promotion;

/**
 * Immutable result of DiscountResolver for a cart/checkout.
 */
final class DiscountResolution
{
    /**
     * @param  list<array{
     *     promotion_id: string,
     *     promotion_name: string,
     *     promotion_code: string|null,
     *     discount_amount: string,
     *     discount_type: string,
     *     eligible_subtotal: string
     * }>  $applications
     * @param  list<array{
     *     cart_item_id: string|null,
     *     product_id: string|null,
     *     product_variant_id: string|null,
     *     original_amount: string,
     *     discount_amount: string,
     *     final_amount: string
     * }>  $lineAllocations
     */
    public function __construct(
        public readonly string $subtotal,
        public readonly string $discountTotal,
        public readonly string $shippingTotal,
        public readonly string $currency,
        public readonly array $applications,
        public readonly array $lineAllocations,
        public readonly bool $freeShipping,
        public readonly ?Promotion $primaryPromotion,
        public readonly ?float $estimatedMarginPercentage,
        public readonly bool $marginRejected,
        public readonly ?string $marginMessage,
    ) {}

    public function grandTotal(string $taxTotal = '0.00'): string
    {
        return bcsub(
            bcadd(bcadd($this->subtotal, $this->shippingTotal, 2), $taxTotal, 2),
            $this->discountTotal,
            2,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toBreakdown(): array
    {
        return [
            'applications' => $this->applications,
            'line_allocations' => $this->lineAllocations,
            'free_shipping' => $this->freeShipping,
            'estimated_margin_percentage' => $this->estimatedMarginPercentage,
            'margin_rejected' => $this->marginRejected,
            'margin_message' => $this->marginMessage,
            'primary_promotion_id' => $this->primaryPromotion?->id,
            'primary_promotion_code' => $this->primaryPromotion?->code,
        ];
    }
}
