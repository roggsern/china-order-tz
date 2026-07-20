<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductMediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductMediaRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::in(array_column(ProductMediaType::cases(), 'value'))],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'file' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_primary', 'is_active'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
