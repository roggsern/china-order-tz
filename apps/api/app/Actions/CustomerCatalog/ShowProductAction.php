<?php

namespace App\Actions\CustomerCatalog;

use App\Models\Product;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowProductAction
{
    public function handle(Product $product): Product
    {
        if ($product->is_demo || ! $product->isPurchasable()) {
            throw new NotFoundHttpException('Product not found.');
        }

        $product->load([
            'commerceChannel:id,name,code,description,is_active',
            'category:id,name,slug',
            'brand:id,name,slug',
            'images' => fn ($query) => $query->orderBy('sort_order'),
            'shippingOptions' => fn ($query) => $query->available()->ordered(),
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['product', 'attributeValues.attribute', 'inventory']),
        ]);

        $product->loadAvg(
            ['reviews as average_rating' => fn ($query) => $query->where('is_approved', true)],
            'rating',
        );

        $product->loadCount(
            ['reviews as review_count' => fn ($query) => $query->where('is_approved', true)],
        );

        return $product;
    }
}
