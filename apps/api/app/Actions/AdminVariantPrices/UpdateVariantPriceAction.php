<?php

namespace App\Actions\AdminVariantPrices;

use App\Http\Requests\Admin\UpdateVariantPriceRequest;
use App\Http\Resources\VariantPriceResource;
use App\Models\VariantPrice;

class UpdateVariantPriceAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateVariantPriceRequest $request, VariantPrice $price): array
    {
        $data = $request->validated();

        if (array_key_exists('currency', $data) && is_string($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $price->fill($data);
        $price->save();

        return (new VariantPriceResource($price->fresh()))->resolve();
    }
}
