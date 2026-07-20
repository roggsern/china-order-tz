<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsFeaturedDisplayStyle;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsFeaturedContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsFeaturedContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsFeaturedContent $featured */
        $featured = $this->route('featuredContent');

        return $this->user()?->can('update', $featured) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_type' => ['sometimes', Rule::enum(CmsFeaturedSourceType::class)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'sort_order' => ['sometimes', 'string', 'max:64'],
            'display_style' => ['sometimes', Rule::enum(CmsFeaturedDisplayStyle::class)],
            'configuration' => ['sometimes', 'array'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
