<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsNavigationShell;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsNavigationShellRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsNavigationShell $shell */
        $shell = $this->route('navigationShell') ?? $this->route('shell');

        return $this->user()?->can('update', $shell) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CmsNavigationShell $shell */
        $shell = $this->route('navigationShell') ?? $this->route('shell');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('cms_navigation_shells', 'slug')->ignore($shell->id),
            ],
            'commerce_context' => ['sometimes', Rule::enum(CmsCommerceContext::class)],
            'navigation_type' => ['sometimes', Rule::enum(CmsNavigationType::class)],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
