<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProductShippingOptionsRequest extends FormRequest
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
            'shipping_options' => ['required', 'array'],
            'shipping_options.*.transport_mode' => ['required', 'string', Rule::enum(ShippingMethod::class)],
            'shipping_options.*.price' => ['required', 'numeric', 'min:0'],
            'shipping_options.*.currency' => ['nullable', 'string', 'size:3'],
            'shipping_options.*.is_available' => ['sometimes', 'boolean'],
            'shipping_options.*.notes' => ['nullable', 'string', 'max:2000'],
            'shipping_options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
