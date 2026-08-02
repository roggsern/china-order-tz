<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;

class ShowProductAction
{
    public function handle(Product $product): Product
    {
        return $product->load(array_merge([
            'commerceChannel',
            'category.productType',
            'category.department',
            'category.parent',
            'brand',
            'supplier',
            'catalogProductType.subcategory',
            'productType',
            'inventory',
            'priceTiers',
            'shippingOptions',
            'variants.attributeValues.attribute',
            'variants.inventory',
            'variants.inventories',
            'variants.priceTiers',
        ], CustomerProductMediaResolver::adminProductEagerLoads()));
    }
}
