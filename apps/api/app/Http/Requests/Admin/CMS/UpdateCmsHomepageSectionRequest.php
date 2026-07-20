<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsHomepageSectionType;
use App\Models\CmsHomepageSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsHomepageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsHomepageSection $section */
        $section = $this->route('section');

        return $this->user()?->can('update', $section) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_type' => ['sometimes', Rule::enum(CmsHomepageSectionType::class)],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
        ];
    }
}
