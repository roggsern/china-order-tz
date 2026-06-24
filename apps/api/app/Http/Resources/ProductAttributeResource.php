<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductAttribute */
class ProductAttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'is_filterable' => $this->is_filterable,
            'sort_order' => $this->sort_order,
            'values' => ProductAttributeValueResource::collection($this->whenLoaded('values')),
        ];
    }
}
