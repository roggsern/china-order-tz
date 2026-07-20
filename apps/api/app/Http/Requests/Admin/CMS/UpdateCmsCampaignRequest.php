<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsCampaign $campaign */
        $campaign = $this->route('campaign');

        return $this->user()?->can('update', $campaign) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CmsCampaign $campaign */
        $campaign = $this->route('campaign');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255', 'alpha_dash',
                Rule::unique('cms_campaigns', 'slug')->ignore($campaign->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'commerce_context' => ['sometimes', Rule::enum(CmsCommerceContext::class)],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'cms_homepage_layout_id' => ['sometimes', 'nullable', 'uuid', 'exists:cms_homepage_layouts,id'],
        ];
    }
}
