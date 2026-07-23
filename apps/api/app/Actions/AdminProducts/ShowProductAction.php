<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;

class ShowProductAction
{
    public function handle(Product $product): Product
    {
        return $product->load([
            'commerceChannel',
            'category.productType',
            'category.department',
            'category.parent',
            'brand',
            'catalogProductType.subcategory',
            'productType',
            'inventory',
            'images',
            'priceTiers',
            'shippingOptions',
            'variants.attributeValues.attribute',
            'variants.inventory',
            'variants.inventories',
            'variants.priceTiers',
        ]);
    }
}
