<?php

namespace App\Http\Resources;

use App\Models\CmsCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsCampaign */
class CmsCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'commerce_context' => $this->commerce_context instanceof \BackedEnum
                ? $this->commerce_context->value
                : $this->commerce_context,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'priority' => (int) $this->priority,
            'is_default' => (bool) $this->is_default,
            'cms_homepage_layout_id' => $this->cms_homepage_layout_id,
            'layout' => $this->whenLoaded('layout', fn () => [
                'id' => $this->layout?->id,
                'name' => $this->layout?->name,
                'slug' => $this->layout?->slug,
                'commerce_context' => $this->layout?->commerce_context instanceof \BackedEnum
                    ? $this->layout->commerce_context->value
                    : $this->layout?->commerce_context,
            ]),
            'hero_slide_ids' => $this->whenLoaded(
                'heroSlides',
                fn () => $this->heroSlides->pluck('id')->values()->all(),
            ),
            'featured_content_ids' => $this->whenLoaded(
                'featuredContents',
                fn () => $this->featuredContents->pluck('id')->values()->all(),
            ),
            'promotion_ids' => $this->whenLoaded(
                'promotions',
                fn () => $this->promotions->pluck('id')->values()->all(),
            ),
            'navigation_shell_ids' => $this->whenLoaded(
                'navigationShells',
                fn () => $this->navigationShells->pluck('id')->values()->all(),
            ),
            'hero_slides_count' => $this->whenCounted('heroSlides'),
            'featured_contents_count' => $this->whenCounted('featuredContents'),
            'promotions_count' => $this->whenCounted('promotions'),
            'navigation_shells_count' => $this->whenCounted('navigationShells'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
