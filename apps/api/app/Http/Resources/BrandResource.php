<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand */
class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'website' => $this->website,
            'country' => $this->country,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'origin' => $category->origin?->value ?? $category->origin,
            ])->values()),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->values()),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
