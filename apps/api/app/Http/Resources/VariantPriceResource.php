<?php

namespace App\Http\Resources;

use App\Enums\VariantPriceType;
use App\Models\VariantPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VariantPrice */
class VariantPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->price_type instanceof VariantPriceType
            ? $this->price_type->value
            : (string) $this->price_type;

        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'price_type' => $type,
            'currency' => strtoupper((string) $this->currency),
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'minimum_quantity' => (int) $this->minimum_quantity,
            'is_active' => (bool) $this->is_active,
            'is_currently_active' => $this->isCurrentlyActive(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
