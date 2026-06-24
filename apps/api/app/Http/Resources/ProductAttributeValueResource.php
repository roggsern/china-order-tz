<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductAttributeValue */
class ProductAttributeValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'slug' => $this->slug,
            'color_code' => $this->color_code,
            'sort_order' => $this->sort_order,
            'attribute' => new ProductAttributeResource($this->whenLoaded('attribute')),
        ];
    }
}
