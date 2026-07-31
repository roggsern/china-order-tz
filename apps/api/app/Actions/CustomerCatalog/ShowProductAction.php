<?php

namespace App\Actions\CustomerCatalog;

use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Features\FeatureAvailabilityService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowProductAction
{
    public function __construct(
        private readonly FeatureAvailabilityService $features,
    ) {}

    public function handle(Product $product): Product
    {
        if ($product->is_demo || ! $product->isVisible()) {
            throw new NotFoundHttpException('Product not found.');
        }

        $product->load(array_merge([
            'commerceChannel:id,name,code,description,is_active',
            'category:id,name,slug',
            'brand:id,name,slug',
            'shippingOptions' => fn ($query) => $query->available()->ordered(),
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->with(array_merge([
                    'product',
                    'attributeValues.attribute',
                    'catalogAttributeValues.attribute',
                    'catalogAttributeValues.option',
                    'prices',
                    'inventories',
                    'inventory',
                ], CustomerProductMediaResolver::variantMediaEagerLoads())),
        ], CustomerProductMediaResolver::catalogEagerLoads()));

        if ($this->features->canUseReviews()) {
            $product->loadAvg(
                ['reviews as average_rating' => fn ($query) => $query->where('is_approved', true)],
                'rating',
            );

            $product->loadCount(
                ['reviews as review_count' => fn ($query) => $query->where('is_approved', true)],
            );
        } else {
            $product->setAttribute('average_rating', null);
            $product->setAttribute('review_count', 0);
        }

        return $product;
    }
}
