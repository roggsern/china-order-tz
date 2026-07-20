<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CatalogAttribute */
class CatalogAttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type?->value ?? $this->type,
            'unit' => $this->unit,
            'is_filterable' => $this->is_filterable,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'options' => CatalogAttributeOptionResource::collection($this->whenLoaded('options')),
            'catalog_product_types' => $this->whenLoaded('catalogProductTypes', fn () => $this->catalogProductTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'is_required' => (bool) ($type->pivot->is_required ?? false),
                'sort_order' => (int) ($type->pivot->sort_order ?? 0),
            ])->values()),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
