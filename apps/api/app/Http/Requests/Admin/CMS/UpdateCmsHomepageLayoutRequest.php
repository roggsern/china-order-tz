<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsHomepageLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsHomepageLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsHomepageLayout $layout */
        $layout = $this->route('layout');

        return $this->user()?->can('update', $layout) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CmsHomepageLayout $layout */
        $layout = $this->route('layout');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('cms_homepage_layouts', 'slug')->ignore($layout->id),
            ],
            'commerce_context' => ['sometimes', Rule::enum(CmsCommerceContext::class)],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
