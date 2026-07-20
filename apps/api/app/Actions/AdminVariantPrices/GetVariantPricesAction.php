<?php

namespace App\Actions\AdminVariantPrices;

use App\Http\Resources\VariantPriceResource;
use App\Models\ProductVariant;

class GetVariantPricesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(ProductVariant $variant): array
    {
        $prices = $variant->prices()
            ->orderBy('price_type')
            ->orderBy('currency')
            ->orderBy('minimum_quantity')
            ->get();

        return VariantPriceResource::collection($prices)->resolve();
    }
}
