<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductType */
class ProductTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku_pattern' => $this->sku_pattern,
            'has_configurations' => $this->has_configurations,
            'allows_price_override' => $this->allows_price_override,
            'allows_moq_pricing' => $this->allows_moq_pricing,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'attributes' => $this->whenLoaded('typeAttributes', function () {
                return $this->typeAttributes->map(function ($typeAttribute) {
                    $attribute = $typeAttribute->attribute;

                    return [
                        'id' => $attribute?->id,
                        'name' => $attribute?->name,
                        'slug' => $attribute?->slug,
                        'type' => $attribute?->type?->value ?? $attribute?->type,
                        'unit' => $attribute?->unit,
                        'validation' => $attribute?->validation,
                        'is_filterable' => $attribute?->is_filterable,
                        'sort_order' => $typeAttribute->sort_order,
                        'is_required' => $typeAttribute->is_required,
                        'participates_in_configuration' => $typeAttribute->participates_in_configuration,
                        'values' => ProductAttributeValueResource::collection(
                            $attribute?->relationLoaded('values') ? $attribute->values : collect()
                        ),
                    ];
                })->values();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
