<?php

namespace App\Http\Requests\Admin\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationVisibility;
use App\Models\CmsNavigationShell;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsNavigationItemRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'visibility' => ['sometimes', Rule::enum(CmsNavigationVisibility::class)],
            'item_type' => ['required', Rule::enum(CmsNavigationItemType::class)],
            'target_type' => ['nullable', Rule::enum(CmsCtaTargetType::class)],
            'target_value' => ['nullable', 'string', 'max:2048'],
            'is_enabled' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'uuid', 'exists:cms_navigation_items,id'],
        ];
    }
}
