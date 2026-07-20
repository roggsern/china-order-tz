<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
        /** @var \App\Models\Brand $brand */
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand)],
            'description' => ['sometimes', 'nullable', 'string'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'banner' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_active', 'is_featured'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
