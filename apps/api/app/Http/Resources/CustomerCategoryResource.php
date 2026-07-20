<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CustomerCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $children = null;
        if ($this->relationLoaded('childrenRecursive')) {
            $children = CustomerCategoryResource::collection($this->childrenRecursive);
        } elseif ($this->relationLoaded('children')) {
            $children = CustomerCategoryResource::collection($this->children);
        }

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'origin' => $this->origin instanceof \BackedEnum
                ? $this->origin->value
                : $this->origin,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'children' => $children,
        ];
    }
}
