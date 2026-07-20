<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductShippingOptionRequest extends FormRequest
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
            'transport_mode' => ['required', 'string', Rule::enum(ShippingMethod::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_available' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
