<?php

namespace App\Services\Purchasing;

use App\Models\Cart;
use App\Models\Product;
use App\Services\Cart\CartProductPricingQuantity;
use App\Support\Http\ApiResponse;

/**
 * Hard purchase-quantity enforcement for checkout, order creation, and Buy Now.
 * Cart construction must not call this.
 *
 * Fail-fast: the first illegal product_id in cart insertion order is rejected.
 * That matches existing coded ValidationException usage (one 422, one code).
 */
final class AssertPurchaseQuantity
{
    public function assertLegal(Product $product, int $eligibleQuantity): PurchaseQuantityEvaluation
    {
        $evaluation = PurchaseQuantityRule::forProduct($product)->evaluate($eligibleQuantity);
        if ($evaluation->isLegal) {
            return $evaluation;
        }

        $this->reject($evaluation);
    }

    public function assertCart(Cart $cart): void
    {
        $aggregates = CartProductPricingQuantity::mapByProductId($cart->items);
        if ($aggregates === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', array_keys($aggregates))
            ->get()
            ->keyBy(fn (Product $product): string => (string) $product->id);

        foreach ($aggregates as $productId => $quantity) {
            $product = $products->get((string) $productId);
            if ($product === null) {
                continue;
            }

            $this->assertLegal($product, $quantity);
        }
    }

    private function reject(PurchaseQuantityEvaluation $evaluation): never
    {
        ApiResponse::throwCodedValidation(
            [
                'purchase_quantity' => ['This product does not meet the purchase quantity rule.'],
            ],
            'purchase_quantity_unsatisfied',
            extra: [
                'data' => [
                    'purchase_quantity' => $evaluation->toCheckoutErrorPayload(),
                ],
            ],
        );
    }
}
