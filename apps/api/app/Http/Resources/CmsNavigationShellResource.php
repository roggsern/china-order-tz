<?php

namespace App\Http\Resources;

use App\Models\CmsNavigationShell;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsNavigationShell */
class CmsNavigationShellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'commerce_context' => $this->commerce_context instanceof \BackedEnum
                ? $this->commerce_context->value
                : $this->commerce_context,
            'navigation_type' => $this->navigation_type instanceof \BackedEnum
                ? $this->navigation_type->value
                : $this->navigation_type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_default' => (bool) $this->is_default,
            'items_count' => $this->whenCounted('items'),
            'items' => CmsNavigationItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
