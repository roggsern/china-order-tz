<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class SubcategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $department = $this->relationLoaded('department') && $this->department
            ? $this->department
            : ($this->relationLoaded('parent') ? $this->parent?->department : null);

        return [
            'id' => $this->id,
            'category_id' => $this->parent_id,
            'department_id' => $this->department_id ?? $department?->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('parent', fn () => $this->parent === null ? null : [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
                'department_id' => $this->parent->department_id,
            ]),
            'department' => $department === null ? null : [
                'id' => $department->id,
                'name' => $department->name,
                'slug' => $department->slug,
                'icon' => $department->icon,
            ],
            'products_count' => $this->whenCounted('products'),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
