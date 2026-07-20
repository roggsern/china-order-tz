<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsHeroContentAlignment;
use App\Enums\CMS\CmsHeroTextTheme;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsHeroSlide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsHeroSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsHeroSlide $slide */
        $slide = $this->route('heroSlide') ?? $this->route('slide');

        return $this->user()?->can('update', $slide) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'headline' => ['sometimes', 'string', 'max:255'],
            'subheadline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'eyebrow_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'desktop_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media,id'],
            'mobile_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media,id'],
            'content_alignment' => ['sometimes', Rule::enum(CmsHeroContentAlignment::class)],
            'text_theme' => ['sometimes', Rule::enum(CmsHeroTextTheme::class)],
            'primary_cta_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'primary_cta_type' => ['sometimes', 'nullable', Rule::enum(CmsCtaTargetType::class)],
            'primary_cta_value' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'secondary_cta_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'secondary_cta_type' => ['sometimes', 'nullable', Rule::enum(CmsCtaTargetType::class)],
            'secondary_cta_value' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_visible' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
