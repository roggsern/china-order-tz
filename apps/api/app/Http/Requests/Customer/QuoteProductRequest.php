<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class QuoteProductRequest extends FormRequest
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
            'configuration_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'promotion_code' => ['nullable', 'string', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'customer_group_id' => ['nullable', 'uuid'],
        ];
    }
}
