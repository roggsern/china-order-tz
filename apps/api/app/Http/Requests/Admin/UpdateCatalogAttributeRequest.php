<?php

namespace App\Http\Requests\Admin;

use App\Enums\CatalogAttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\CatalogAttribute $catalogAttribute */
        $catalogAttribute = $this->route('catalogAttribute');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('catalog_attributes', 'slug')->ignore($catalogAttribute),
            ],
            'type' => ['required', Rule::enum(CatalogAttributeType::class)],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
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
