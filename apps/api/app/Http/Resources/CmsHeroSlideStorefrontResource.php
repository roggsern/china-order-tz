<?php

namespace App\Http\Resources;

use App\Models\CmsHeroSlide;
use App\Services\CMS\CmsCtaTargetValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storefront-safe hero slide payload — no admin-only fields.
 *
 * @mixin CmsHeroSlide
 */
class CmsHeroSlideStorefrontResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CmsCtaTargetValidationService $cta */
        $cta = app(CmsCtaTargetValidationService::class);

        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'subheadline' => $this->subheadline,
            'eyebrow_text' => $this->eyebrow_text,
            'description' => $this->description,
            'desktop_media' => $this->when(
                $this->relationLoaded('desktopMedia') && $this->desktopMedia !== null,
                fn () => new CmsMediaResource($this->desktopMedia),
            ),
            'mobile_media' => $this->when(
                $this->relationLoaded('mobileMedia') && $this->mobileMedia !== null,
                fn () => new CmsMediaResource($this->mobileMedia),
            ),
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
            'secondary_cta' => $cta->resolveForStorefront(
                $this->secondary_cta_type,
                $this->secondary_cta_label,
                $this->secondary_cta_value,
            ),
            'position' => (int) $this->position,
        ];
    }
}
