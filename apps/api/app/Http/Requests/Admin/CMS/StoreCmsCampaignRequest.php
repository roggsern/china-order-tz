<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CmsCampaign::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:cms_campaigns,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'commerce_context' => ['required', Rule::enum(CmsCommerceContext::class)],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'cms_homepage_layout_id' => ['nullable', 'uuid', 'exists:cms_homepage_layouts,id'],
        ];
    }
}
