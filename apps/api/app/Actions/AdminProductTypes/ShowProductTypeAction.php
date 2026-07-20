<?php

namespace App\Actions\AdminProductTypes;

use App\Models\ProductType;

class ShowProductTypeAction
{
    public function handle(ProductType $productType): ProductType
    {
        $productType->load([
            'typeAttributes' => fn ($query) => $query->orderBy('sort_order'),
            'typeAttributes.attribute.values',
            'attributeDependencies.rules',
        ]);

        return $productType;
    }
}
