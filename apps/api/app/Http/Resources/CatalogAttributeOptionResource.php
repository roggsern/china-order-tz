<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CatalogAttributeOption */
class CatalogAttributeOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog_attribute_id' => $this->catalog_attribute_id,
            'value' => $this->value,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
