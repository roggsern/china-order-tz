<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsHomepageSectionType;
use App\Models\CmsHomepageSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsHomepageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CmsHomepageSection::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_type' => ['required', Rule::enum(CmsHomepageSectionType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
        ];
    }
}
