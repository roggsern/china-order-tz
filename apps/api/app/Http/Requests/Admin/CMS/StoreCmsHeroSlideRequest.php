<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsHeroContentAlignment;
use App\Enums\CMS\CmsHeroTextTheme;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsHeroSlide;
use App\Support\Security\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsHeroSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CmsHeroSlide::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'headline' => ['required', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:255'],
            'eyebrow_text' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'desktop_media_id' => ['nullable', 'uuid', 'exists:media,id'],
            'mobile_media_id' => ['nullable', 'uuid', 'exists:media,id'],
            'content_alignment' => ['sometimes', Rule::enum(CmsHeroContentAlignment::class)],
            'text_theme' => ['sometimes', Rule::enum(CmsHeroTextTheme::class)],
            'primary_cta_label' => ['nullable', 'string', 'max:120'],
            'primary_cta_type' => ['nullable', Rule::enum(CmsCtaTargetType::class)],
            'primary_cta_value' => ['nullable', 'string', 'max:2048'],
            'secondary_cta_label' => ['nullable', 'string', 'max:120'],
            'secondary_cta_type' => ['nullable', Rule::enum(CmsCtaTargetType::class)],
            'secondary_cta_value' => ['nullable', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_visible' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('description') && is_string($this->input('description'))) {
            $this->merge([
                'description' => HtmlSanitizer::sanitize($this->input('description')),
            ]);
        }
    }
}
