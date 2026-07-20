<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CatalogProductType */
class CatalogProductTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subcategory = $this->relationLoaded('subcategory') ? $this->subcategory : null;
        $category = null;
        $department = null;

        if ($subcategory !== null) {
            $subcategory->loadMissing(['parent.department', 'department']);

            if ($subcategory->parent_id !== null) {
                $category = $subcategory->parent;
                $department = $category?->department ?? $subcategory->department;
            } else {
                $category = $subcategory;
                $department = $subcategory->department;
            }
        }

        return [
            'id' => $this->id,
            'subcategory_id' => $this->subcategory_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'image' => $this->image,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'subcategory' => $subcategory === null ? null : [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'slug' => $subcategory->slug,
                'parent_id' => $subcategory->parent_id,
                'department_id' => $subcategory->department_id,
            ],
            'category' => $category === null ? null : [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'department_id' => $category->department_id,
            ],
            'department' => $department === null ? null : [
                'id' => $department->id,
                'name' => $department->name,
                'slug' => $department->slug,
                'icon' => $department->icon,
            ],
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
