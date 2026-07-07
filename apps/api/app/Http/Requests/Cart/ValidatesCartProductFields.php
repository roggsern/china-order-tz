<?php

namespace App\Http\Requests\Cart;

use Illuminate\Validation\Rule;

trait ValidatesCartProductFields
{
    /**
     * @return array<string, mixed>
     */
    protected function cartProductRules(): array
    {
        return [
            'product_id' => [
                'required',
                'uuid',
                Rule::exists('products', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'variant_id' => [
                'nullable',
                'uuid',
                Rule::exists('product_variants', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
