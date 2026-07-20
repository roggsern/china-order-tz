<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsHomepageLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsHomepageLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CmsHomepageLayout::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:cms_homepage_layouts,slug'],
            'commerce_context' => ['required', Rule::enum(CmsCommerceContext::class)],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
