<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Enums\CatalogAttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogAttributeRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::CONFIGURATION_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:catalog_attributes,slug'],
            'type' => ['required', Rule::enum(CatalogAttributeType::class)],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'array'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'options.*.slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_filterable', 'is_required', 'is_active'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
