<?php

namespace App\Http\Resources;

use App\Models\CmsHomepageLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsHomepageLayout */
class CmsHomepageLayoutResource extends JsonResource
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
            'status' => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,
            'is_default' => (bool) $this->is_default,
            'sections_count' => $this->whenCounted('sections'),
            'sections' => CmsHomepageSectionResource::collection($this->whenLoaded('sections')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
