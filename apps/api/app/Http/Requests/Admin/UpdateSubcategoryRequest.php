<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubcategoryRequest extends FormRequest
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
        /** @var \App\Models\Category $subcategory */
        $subcategory = $this->route('subcategory');

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'uuid', 'exists:categories,id', Rule::notIn([$subcategory->id])],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($subcategory),
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
