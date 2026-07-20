<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductShippingOption */
class ProductShippingOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mode = $this->transport_mode;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'transport_mode' => $mode instanceof \BackedEnum ? $mode->value : (string) $mode,
            'transport_mode_label' => $mode instanceof \App\Enums\ShippingMethod
                ? match ($mode) {
                    \App\Enums\ShippingMethod::Air => 'Air Freight',
                    \App\Enums\ShippingMethod::Sea => 'Sea Freight',
                }
                : null,
            'price' => $this->price,
            'currency' => $this->currency,
            'is_available' => (bool) $this->is_available,
            'notes' => $this->notes,
            'sort_order' => (int) $this->sort_order,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
