<?php

namespace App\Http\Requests\Admin;

use App\Enums\CatalogOrigin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        /** @var \App\Models\Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'uuid', 'exists:departments,id'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id', Rule::notIn([$category->id])],
            'origin' => ['sometimes', 'nullable', Rule::enum(CatalogOrigin::class)],
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
