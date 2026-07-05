<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'shipping_address_id' => ['required', 'exists:shipping_addresses,id'],
            'coupon_id' => ['nullable', 'exists:coupons,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variant_id' => ['nullable'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
