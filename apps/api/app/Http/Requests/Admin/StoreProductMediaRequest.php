<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductMediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductMediaRequest extends FormRequest
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
        $type = ProductMediaType::tryFromMixed($this->input('type')) ?? ProductMediaType::Image;

        $rules = [
            'type' => ['required', 'string', Rule::in(array_column(ProductMediaType::cases(), 'value'))],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];

        if ($type === ProductMediaType::Video) {
            $rules['url'] = ['required', 'string', 'max:2048', 'url'];
            $rules['file'] = ['prohibited'];
        } else {
            $rules['file'] = ['required_without:url', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $rules['url'] = ['required_without:file', 'nullable', 'string', 'max:2048'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge(['type' => $this->hasFile('file') ? 'image' : 'video']);
        }

        foreach (['is_primary', 'is_active'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
