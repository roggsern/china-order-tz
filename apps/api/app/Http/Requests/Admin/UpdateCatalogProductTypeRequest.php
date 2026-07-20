<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogProductTypeRequest extends FormRequest
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
        /** @var \App\Models\CatalogProductType $catalogProductType */
        $catalogProductType = $this->route('catalogProductType');

        return [
            'name' => ['required', 'string', 'max:255'],
            'subcategory_id' => ['required', 'uuid', 'exists:categories,id'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('catalog_product_types', 'slug')->ignore($catalogProductType),
            ],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && ! is_bool($this->input('is_active'))) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
