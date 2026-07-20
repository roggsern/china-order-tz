<?php

namespace App\Http\Requests\Admin;

use App\Enums\CatalogOrigin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'uuid', 'exists:departments,id'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'origin' => ['sometimes', 'nullable', Rule::enum(CatalogOrigin::class)],
            'store_id' => ['sometimes', 'nullable', 'uuid', 'exists:stores,id'],
            'product_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:product_types,id'],
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
