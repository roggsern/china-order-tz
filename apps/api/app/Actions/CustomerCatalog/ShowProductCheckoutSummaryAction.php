<?php

namespace App\Actions\CustomerCatalog;

use App\Models\Product;
use App\Http\Resources\CustomerProductCardResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Listing-grade product payload for checkout cart validation.
 * Reuses purchasability/stock presentation without PDP media/attribute graphs.
 */
class ShowProductCheckoutSummaryAction
{
    public function handle(Product $product): Product
    {
        if ($product->is_demo || ! $product->isVisible()) {
            throw new NotFoundHttpException('Product not found.');
        }

        $product->load(CustomerProductCardResource::listingEagerLoads());

        // Checkout validation does not need review aggregates.
        $product->setAttribute('average_rating', null);
        $product->setAttribute('review_count', 0);

        return $product;
    }
}
