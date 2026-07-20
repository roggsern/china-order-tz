<?php

namespace App\Http\Resources;

use App\Enums\CMS\CmsHomepageSectionType;
use App\Models\CmsHomepageSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsHomepageSection */
class CmsHomepageSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStorefront = $request->is('api/v1/storefront/*');

        return [
            'id' => $this->id,
            'cms_homepage_layout_id' => $this->cms_homepage_layout_id,
            'section_type' => $this->section_type instanceof \BackedEnum
                ? $this->section_type->value
                : $this->section_type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'position' => (int) $this->position,
            'is_visible' => (bool) $this->is_visible,
            'configuration' => $this->configuration ?? [],
            'hero_slides' => $this->when(
                $this->relationLoaded('heroSlides')
                    && $this->section_type === CmsHomepageSectionType::Hero,
                function () use ($isStorefront) {
                    if ($isStorefront) {
                        return CmsHeroSlideStorefrontResource::collection($this->heroSlides);
                    }

                    return CmsHeroSlideResource::collection($this->heroSlides);
                },
            ),
            'featured_contents' => $this->when(
                $this->relationLoaded('featuredContents')
                    && $this->section_type instanceof CmsHomepageSectionType
                    && $this->section_type->supportsFeaturedContent(),
                fn () => CmsFeaturedContentResource::collection($this->featuredContents),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
