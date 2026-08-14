<?php

namespace App\Services\Cart;

use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use App\Support\Http\ApiResponse;
use Illuminate\Validation\ValidationException;
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
                $this->reject([
                    'product_variant_id' => ['Product variant not found.'],
                ], 'not_found');
            }

            $product = $variant->product;

            if ($product === null) {
                $this->reject([
                    'product_variant_id' => ['Product variant has no parent product.'],
                ], 'not_found');
            }

            if (filled($productId) && $product->id !== $productId) {
                $this->reject([
                    'product_variant_id' => [
                        'Product variant does not belong to the selected product.',
                    ],
                ]);
            }
        } else {
            $product = Product::query()
                ->with(['inventory', 'variants.prices', 'variants.inventories'])
                ->find($productId);

            if ($product === null) {
                $this->reject([
                    'product_id' => ['Product not found.'],
                ], 'not_found');
            }
        }

        $path = $this->purchasabilityPolicy->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            if ($variant === null) {
                $this->reject([
                    'product_variant_id' => ['A product variant is required.'],
                ]);
            }

            if (! $variant->is_active || ! $this->purchasabilityPolicy->isSellableVariant($variant, $product)) {
                $this->reject([
                    'product_variant_id' => ['Product variant is not available.'],
                ]);
            }

            if (! $this->purchasabilityPolicy->isPurchasable($product)) {
                $this->reject([
                    'product_id' => ['Product is not available.'],
                ]);
            }

            return;
        }

        if ($variant !== null) {
            $this->reject([
                'product_variant_id' => [
                    'This product is sold as a simple product and does not accept a variant selection.',
                ],
            ]);
        }

        if (! $this->purchasabilityPolicy->isPurchasable($product)) {
            $this->reject([
                'product_id' => ['Product is not available.'],
            ]);
        }
    }

    /**
     * Selection / purchasability rule — same messages + 422; Contract v1 code is additive.
     *
     * @param  array<string, list<string>|string>  $messages
     */
    private function reject(array $messages, string $code = 'business_rule_violated'): never
    {
        $exception = ValidationException::withMessages($messages);
        $errors = $exception->errors();
        $first = collect($errors)->flatten()->first();

        $exception->response = ApiResponse::error(
            message: is_string($first) && $first !== '' ? $first : $exception->getMessage(),
            code: $code,
            status: 422,
            extra: ['errors' => $errors],
        );

        throw $exception;
    }
}
