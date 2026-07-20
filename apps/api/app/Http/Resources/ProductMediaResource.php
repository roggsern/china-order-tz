<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductMedia */
class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'type' => $this->type?->value ?? $this->type,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'alt_text' => $this->alt_text,
            'title' => $this->title,
            'sort_order' => (int) $this->sort_order,
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
