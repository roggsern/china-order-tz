<?php

namespace App\Actions\CustomerCatalog;

use App\Http\Requests\Customer\QuoteProductRequest;
use App\Models\Product;
use App\Services\Pricing\DTOs\PriceBreakdown;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\ResolvePrice;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuoteCustomerProductPriceAction
{
    public function __construct(
        private readonly ResolvePrice $resolvePrice,
    ) {}

    public function handle(QuoteProductRequest $request, Product $product): PriceBreakdown
    {
        if ($product->is_demo || ! $product->isPurchasable()) {
            throw new NotFoundHttpException('Product not found.');
        }

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
