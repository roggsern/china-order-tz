<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
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
        $variantId = $this->route('variant')?->id ?? $this->route('variant');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'attribute_values' => ['sometimes', 'array'],
            'attribute_values.*.catalog_attribute_id' => ['required', 'uuid', 'exists:catalog_attributes,id'],
            'attribute_values.*.option_id' => ['sometimes', 'nullable', 'uuid', 'exists:catalog_attribute_options,id'],
            'attribute_values.*.value_text' => ['sometimes', 'nullable', 'string'],
            'attribute_values.*.value_number' => ['sometimes', 'nullable', 'numeric'],
            'attribute_values.*.value_boolean' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && ! is_bool($this->input('is_active'))) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('is_default') && ! is_bool($this->input('is_default'))) {
            $this->merge([
                'is_default' => filter_var($this->input('is_default'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
