<?php

namespace App\Http\Resources;

use App\Models\CmsNavigationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsNavigationItem */
class CmsNavigationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'navigation_shell_id' => $this->navigation_shell_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'icon' => $this->icon,
            'position' => (int) $this->position,
            'visibility' => $this->visibility instanceof \BackedEnum
                ? $this->visibility->value
                : $this->visibility,
            'item_type' => $this->item_type instanceof \BackedEnum
                ? $this->item_type->value
                : $this->item_type,
            'target_type' => $this->target_type instanceof \BackedEnum
                ? $this->target_type->value
                : $this->target_type,
            'target_value' => $this->target_value,
            'is_enabled' => (bool) $this->is_enabled,
            'children' => CmsNavigationItemResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
