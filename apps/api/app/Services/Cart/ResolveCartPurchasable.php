<?php

namespace App\Services\Cart;

use App\Enums\PurchasabilityPath;
use App\Enums\ShippingMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\StockResolver;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Validation\ValidationException;

/**
 * Cart Engine purchasable resolution (ADR 053 / ADR 054 / ADR 055).
 *
 * Unit price: CommercePricingResolver (Catalog → Quote).
 * Stock: StockResolver (Catalog Stock read).
 * Lifecycle / shipping / purchasability unchanged.
 */
class ResolveCartPurchasable
{
    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly CommercePricingResolver $commercePricingResolver,
        private readonly StockResolver $stockResolver,
    ) {}

    /**
     * @return array{
     *     product: Product,
     *     variant: ProductVariant|null,
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

        if (filled($variantId)) {
            return $this->resolveVariantLine($productId, (string) $variantId, $quantity, $currency, $shippingMethod);
        }

        if (filled($productId)) {
            return $this->resolveSimpleLine((string) $productId, $quantity, $currency, $shippingMethod);
        }

        throw ValidationException::withMessages([
            'product_id' => ['A product or product variant is required.'],
        ]);
    }

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
    private function resolveVariantLine(
        ?string $productId,
        string $variantId,
        int $quantity,
        string $currency,
        ?string $shippingMethod,
    ): array {
        $variant = ProductVariant::query()
            ->with(['product.commerceChannel', 'product.inventory', 'prices', 'inventories', 'inventory'])
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

        $this->assertProductLifecycleEligible($product);

        // Variant selection is rejected only when the product is already a valid Simple sell path.
        if (
            $this->purchasabilityPolicy->resolvePath($product) === PurchasabilityPath::Simple
            && $this->purchasabilityPolicy->isPurchasable($product)
        ) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['This product is sold as a simple product and does not accept a variant selection.'],
            ]);
        }

        $priced = $this->commercePricingResolver->resolveCommerceUnitPrice(
            $product,
            $variant,
            new CommercePricingContext(
                currency: $currency,
                quantity: $quantity,
                allowLegacyVariantFallback: true,
            ),
        );

        if (! $priced->resolved || (float) $priced->unitPrice <= 0) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['No active retail price found for this variant.'],
            ]);
        }

        $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
        $available = $stock->quantityAvailable;

        if (! $stock->resolved || $available < $quantity) {
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
            'unit_price' => $priced->unitPrice,
            'currency' => $currency,
            'shipping_method' => $resolvedShippingMethod,
            'shipping_price' => $resolvedShippingPrice,
        ];
    }

    /**
     * @return array{
     *     product: Product,
     *     variant: null,
     *     unit_price: string,
     *     currency: string,
     *     shipping_method: ShippingMethod|null,
     *     shipping_price: string|null
     * }
     */
    private function resolveSimpleLine(
        string $productId,
        int $quantity,
        string $currency,
        ?string $shippingMethod,
    ): array {
        $product = Product::query()
            ->with(['commerceChannel', 'inventory', 'variants.prices', 'variants.inventories'])
            ->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found.'],
            ]);
        }

        $this->assertProductPurchasable($product);

        if ($this->purchasabilityPolicy->resolvePath($product) !== PurchasabilityPath::Simple) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['A product variant is required for this product.'],
            ]);
        }

        $priced = $this->commercePricingResolver->resolveCommerceUnitPrice(
            $product,
            null,
            new CommercePricingContext(
                currency: $currency,
                quantity: $quantity,
                allowLegacyVariantFallback: true,
            ),
        );

        if (! $priced->resolved || (float) $priced->unitPrice <= 0) {
            throw ValidationException::withMessages([
                'product_id' => ['No valid base price found for this product.'],
            ]);
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        $available = $stock->quantityAvailable;

        if (! $stock->resolved || $available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => [
                    $available < 1
                        ? 'Selected product is out of stock.'
                        : "Only {$available} unit(s) available for this product.",
                ],
            ]);
        }

        [$resolvedShippingMethod, $resolvedShippingPrice] = $this->optionalShipping(
            $product,
            $shippingMethod,
        );

        return [
            'product' => $product,
            'variant' => null,
            'unit_price' => $priced->unitPrice,
            'currency' => $currency,
            'shipping_method' => $resolvedShippingMethod,
            'shipping_price' => $resolvedShippingPrice,
        ];
    }

    private function assertProductPurchasable(Product $product): void
    {
        if (! $this->purchasabilityPolicy->isPurchasable($product)) {
            throw ValidationException::withMessages([
                'product_id' => ['Product is not available.'],
            ]);
        }
    }

    private function assertProductLifecycleEligible(Product $product): void
    {
        if (! $this->purchasabilityPolicy->isLifecycleEligible($product)) {
            throw ValidationException::withMessages([
                'product_id' => ['Product is not available.'],
            ]);
        }
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
