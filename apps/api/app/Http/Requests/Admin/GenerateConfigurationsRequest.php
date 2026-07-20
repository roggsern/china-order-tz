<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateConfigurationsRequest extends FormRequest
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
            'selected_values' => ['required', 'array', 'min:1'],
            'selected_values.*' => ['required', 'array', 'min:1'],
            'selected_values.*.*' => ['uuid', 'exists:product_attribute_values,id'],
            'base_sku' => ['nullable', 'string', 'max:100'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
