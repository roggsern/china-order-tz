<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'parent_id' => $this->parent_id,
            'store_id' => $this->store_id,
            'origin' => $this->origin?->value ?? $this->origin,
            'product_type_id' => $this->product_type_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
                'slug' => $this->department?->slug,
                'icon' => $this->department?->icon,
            ]),
            'store' => $this->whenLoaded('store', fn () => $this->store === null ? null : [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ]),
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->whenCounted('products'),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
