<?php

namespace App\Services\Cart;

use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Validation\Validator;

/**
 * HTTP cart/buy-now selection validation (ADR 053).
 * Path is determined server-side via ProductPurchasabilityPolicy — never by client flags.
 */
final class ValidateCartProductSelection
{
    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
    ) {}

    public function validate(Validator $validator, ?string $productId, ?string $variantId): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (! filled($productId) && ! filled($variantId)) {
            $validator->errors()->add('product_id', 'A product is required.');

            return;
        }

        $variant = null;
        $product = null;

        if (filled($variantId)) {
            $variant = ProductVariant::query()
                ->with(['product.inventory', 'product.variants.prices', 'product.variants.inventories', 'prices', 'inventories'])
                ->find($variantId);

            if ($variant === null) {
                $validator->errors()->add('product_variant_id', 'Product variant not found.');

                return;
            }

            $product = $variant->product;

            if ($product === null) {
                $validator->errors()->add('product_variant_id', 'Product variant has no parent product.');

                return;
            }

            if (filled($productId) && $product->id !== $productId) {
                $validator->errors()->add(
                    'product_variant_id',
                    'Product variant does not belong to the selected product.',
                );

                return;
            }
        } else {
            $product = Product::query()
                ->with(['inventory', 'variants.prices', 'variants.inventories'])
                ->find($productId);

            if ($product === null) {
                $validator->errors()->add('product_id', 'Product not found.');

                return;
            }
        }

        $path = $this->purchasabilityPolicy->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            if ($variant === null) {
                $validator->errors()->add(
                    'product_variant_id',
                    'A product variant is required.',
                );

                return;
            }

            if (! $variant->is_active || ! $this->purchasabilityPolicy->isSellableVariant($variant)) {
                $validator->errors()->add(
                    'product_variant_id',
                    'Product variant is not available.',
                );

                return;
            }

            if (! $this->purchasabilityPolicy->isPurchasable($product)) {
                $validator->errors()->add('product_id', 'Product is not available.');
            }

            return;
        }

        if ($variant !== null) {
            $validator->errors()->add(
                'product_variant_id',
                'This product is sold as a simple product and does not accept a variant selection.',
            );

            return;
        }

        if (! $this->purchasabilityPolicy->isPurchasable($product)) {
            $validator->errors()->add('product_id', 'Product is not available.');
        }
    }
}
