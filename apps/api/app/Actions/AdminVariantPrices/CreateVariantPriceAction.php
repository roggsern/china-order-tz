<?php

namespace App\Actions\AdminVariantPrices;

use App\Http\Requests\Admin\StoreVariantPriceRequest;
use App\Http\Resources\VariantPriceResource;
use App\Models\ProductVariant;
use App\Models\VariantPrice;

class CreateVariantPriceAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(StoreVariantPriceRequest $request, ProductVariant $variant): array
    {
        $data = $request->validated();

        $price = VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => $data['price_type'],
            'currency' => strtoupper($data['currency']),
            'amount' => $data['amount'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'minimum_quantity' => (int) ($data['minimum_quantity'] ?? 1),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        return (new VariantPriceResource($price))->resolve();
    }
}
