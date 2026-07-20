<?php

namespace App\Http\Resources;

use App\Models\CmsHeroSlide;
use App\Services\CMS\CmsCtaTargetValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsHeroSlide */
class CmsHeroSlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CmsCtaTargetValidationService $cta */
        $cta = app(CmsCtaTargetValidationService::class);

        return [
            'id' => $this->id,
            'cms_homepage_section_id' => $this->cms_homepage_section_id,
            'name' => $this->name,
            'headline' => $this->headline,
            'subheadline' => $this->subheadline,
            'eyebrow_text' => $this->eyebrow_text,
            'description' => $this->description,
            'desktop_media_id' => $this->desktop_media_id,
            'mobile_media_id' => $this->mobile_media_id,
            'desktop_media' => new CmsMediaResource($this->whenLoaded('desktopMedia')),
            'mobile_media' => new CmsMediaResource($this->whenLoaded('mobileMedia')),
            'content_alignment' => $this->content_alignment instanceof \BackedEnum
                ? $this->content_alignment->value
                : $this->content_alignment,
            'text_theme' => $this->text_theme instanceof \BackedEnum
                ? $this->text_theme->value
                : $this->text_theme,
            'primary_cta' => $cta->resolveForStorefront(
                $this->primary_cta_type,
                $this->primary_cta_label,
                $this->primary_cta_value,
            ),
            'primary_cta_label' => $this->primary_cta_label,
            'primary_cta_type' => $this->primary_cta_type instanceof \BackedEnum
                ? $this->primary_cta_type->value
                : $this->primary_cta_type,
            'primary_cta_value' => $this->primary_cta_value,
            'secondary_cta' => $cta->resolveForStorefront(
                $this->secondary_cta_type,
                $this->secondary_cta_label,
                $this->secondary_cta_value,
            ),
            'secondary_cta_label' => $this->secondary_cta_label,
            'secondary_cta_type' => $this->secondary_cta_type instanceof \BackedEnum
                ? $this->secondary_cta_type->value
                : $this->secondary_cta_type,
            'secondary_cta_value' => $this->secondary_cta_value,
            'position' => (int) $this->position,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_visible' => (bool) $this->is_visible,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
