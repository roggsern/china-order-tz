<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsNavigationShell;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsNavigationShellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CmsNavigationShell::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('cms_navigation_shells', 'slug')],
            'commerce_context' => ['required', Rule::enum(CmsCommerceContext::class)],
            'navigation_type' => ['required', Rule::enum(CmsNavigationType::class)],
            'status' => ['sometimes', Rule::enum(CmsStatus::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
