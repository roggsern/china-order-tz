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
                'nullable',
                'uuid',
                Rule::exists('products', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'product_variant_id' => [
                'nullable',
                'uuid',
                Rule::exists('product_variants', 'id')
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
            /** Alias for product_variant_id. */
            'configuration_id' => [
                'nullable',
                'uuid',
                Rule::exists('product_variants', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'shipping_method' => ['nullable', 'in:air,sea'],
        ];
    }

    protected function prepareCartProductValidation(): void
    {
        $variantId = $this->input('product_variant_id')
            ?? $this->input('variant_id')
            ?? $this->input('configuration_id');

        if (filled($variantId)) {
            $this->merge([
                'product_variant_id' => $variantId,
                'variant_id' => $variantId,
            ]);
        }

        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    public function withCartVariantValidator($validator): void
    {
        $validator->after(function ($validator) {
            $variantId = $this->input('product_variant_id')
                ?? $this->input('variant_id')
                ?? $this->input('configuration_id');

            if (! filled($variantId)) {
                $validator->errors()->add(
                    'product_variant_id',
                    'A product variant is required.',
                );
            }
        });
    }
}
