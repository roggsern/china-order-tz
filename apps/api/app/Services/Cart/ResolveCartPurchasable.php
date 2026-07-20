<?php

namespace App\Services\Cart;

use App\Enums\ShippingMethod;
use App\Enums\VariantPriceType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Cart Engine purchasable resolution.
 * Price: VariantPrice (retail). Stock: VariantInventory (MAIN).
 */
class ResolveCartPurchasable
{
    /**
     * @return array{
     *     product: Product,
     *     variant: ProductVariant,
     *     unit_price: string,
     *     currency: string,
     *     shipping_method: ShippingMethod|null,
     *     shipping_price: string|null
     * }
     */
    public function handle(
        ?string $productId,
        ?string $variantId,
        int $quantity,
        string $currency = 'TZS',
        ?string $shippingMethod = null,
    ): array {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $currency = strtoupper($currency);

        if (! filled($variantId)) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['A product variant is required.'],
            ]);
        }

        $variant = ProductVariant::query()
            ->with(['product.supplier', 'prices', 'inventories'])
            ->find($variantId);

        if ($variant === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['Product variant not found.'],
            ]);
        }

        if (! $variant->is_active) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['Product variant is not available.'],
            ]);
        }

        $product = $variant->product;

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['Product variant has no parent product.'],
            ]);
        }

        if (filled($productId) && $product->id !== $productId) {
            throw ValidationException::withMessages([
                'product_id' => ['Product does not match the selected variant.'],
            ]);
        }

        if ($product->is_demo || ! $product->isPurchasable()) {
            throw ValidationException::withMessages([
                'product_id' => ['Product is not available.'],
            ]);
        }

        $retail = $variant->prices
            ->first(function ($price) use ($currency) {
                $type = $price->price_type instanceof VariantPriceType
                    ? $price->price_type
                    : VariantPriceType::tryFrom((string) $price->price_type);

                return $type === VariantPriceType::Retail
                    && strtoupper((string) $price->currency) === $currency
                    && $price->isCurrentlyActive();
            });

        if ($retail === null) {
            $retail = $variant->retailPrice($currency);
        }

        if ($retail === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['No active retail price found for this variant.'],
            ]);
        }

        $inventory = $variant->inventories
            ->first(fn ($row) => $row->warehouse_code === 'MAIN' && $row->is_active)
            ?? $variant->mainInventory();

        $available = $inventory?->available() ?? 0;

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => [
                    $available < 1
                        ? 'Selected variant is out of stock.'
                        : "Only {$available} unit(s) available for this variant.",
                ],
            ]);
        }

        [$resolvedShippingMethod, $resolvedShippingPrice] = $this->optionalShipping(
            $product,
            $shippingMethod,
        );

        return [
            'product' => $product,
            'variant' => $variant,
            'unit_price' => number_format((float) $retail->amount, 2, '.', ''),
            'currency' => $currency,
            'shipping_method' => $resolvedShippingMethod,
            'shipping_price' => $resolvedShippingPrice,
        ];
    }

    /**
     * Cart engine does not require shipping; checkout may enforce later.
     *
     * @return array{0: ShippingMethod|null, 1: string|null}
     */
    private function optionalShipping(Product $product, ?string $shippingMethod): array
    {
        if (! filled($shippingMethod)) {
            return [null, null];
        }

        if (! $product->requiresChinaShipping()) {
            throw ValidationException::withMessages([
                'shipping_method' => ['Shipping method is not required for this product.'],
            ]);
        }

        $method = ShippingMethod::tryFrom($shippingMethod);

        if ($method === null) {
            throw ValidationException::withMessages([
                'shipping_method' => ['Invalid shipping method selected.'],
            ]);
        }

        $shippingPrice = $product->shippingPriceForMethod($method->value);

        if ($shippingPrice === null) {
            throw ValidationException::withMessages([
                'shipping_method' => ['Selected shipping method is not available for this product.'],
            ]);
        }

        return [$method, $shippingPrice];
    }
}
