<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\QuoteProductPriceRequest;
use App\Models\Product;
use App\Services\Pricing\DTOs\PriceBreakdown;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\ResolvePrice;

class QuoteProductPriceAction
{
    public function __construct(
        private readonly ResolvePrice $resolvePrice,
    ) {}

    public function handle(QuoteProductPriceRequest $request, Product $product): PriceBreakdown
    {
        $validated = $request->validated();

        return $this->resolvePrice->handle(
            $product->loadMissing(['productType']),
            new PriceQuoteInput(
                productId: $product->id,
                configurationId: $validated['configuration_id'] ?? null,
                quantity: (int) $validated['quantity'],
                promotionCode: $validated['promotion_code'] ?? null,
                couponCode: $validated['coupon_code'] ?? null,
                customerGroupId: $validated['customer_group_id'] ?? null,
            ),
        );
    }
}
