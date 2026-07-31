<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\Validator;

trait ValidatesProductVariantMediaBinding
{
    protected function validateProductVariantMediaBinding(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('product_variant_id')) {
                return;
            }

            $variantId = $this->input('product_variant_id');
            if ($variantId === null || $variantId === '') {
                return;
            }

            if ($validator->errors()->has('product_variant_id')) {
                return;
            }

            /** @var Product|null $product */
            $product = $this->route('product');
            if (! $product instanceof Product) {
                $validator->errors()->add('product_variant_id', 'Product context is required.');

                return;
            }

            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('product_id', $product->id)
                ->first();

            if ($variant === null) {
                $validator->errors()->add(
                    'product_variant_id',
                    'The selected variant does not belong to this product.',
                );

                return;
            }

            if (! $variant->is_active) {
                $validator->errors()->add(
                    'product_variant_id',
                    'The selected variant must be active.',
                );
            }
        });
    }
}
