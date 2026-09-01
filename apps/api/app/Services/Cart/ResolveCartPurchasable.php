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
use App\Support\Http\ApiResponse;
use Illuminate\Validation\ValidationException;

/**
 * Cart Engine purchasable resolution (ADR 053 / ADR 054 / ADR 055).
 *
 * Unit price: CommercePricingResolver (Catalog → Quote).
 * Stock: StockResolver (Catalog Stock read) — always the line/SKU quantity.
 * Pricing quantity: optional same-product aggregate for volume-tier eligibility.
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
        ?int $pricingQuantity = null,
    ): array {
        if ($quantity < 1) {
            $this->reject([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $currency = strtoupper($currency);
        $tierQuantity = ($pricingQuantity !== null && $pricingQuantity >= 1)
            ? $pricingQuantity
            : $quantity;

        if (filled($variantId)) {
            return $this->resolveVariantLine(
                $productId,
                (string) $variantId,
                $quantity,
                $currency,
                $shippingMethod,
                $tierQuantity,
            );
        }

        if (filled($productId)) {
            return $this->resolveSimpleLine(
                (string) $productId,
                $quantity,
                $currency,
                $shippingMethod,
                $tierQuantity,
            );
        }

        $this->reject([
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
        int $pricingQuantity,
    ): array {
        $variant = ProductVariant::query()
            ->with(['product.commerceChannel', 'product.inventory', 'prices', 'inventories', 'inventory'])
            ->find($variantId);

        if ($variant === null) {
            $this->reject([
                'product_variant_id' => ['Product variant not found.'],
            ], 'not_found');
        }

        if (! $variant->is_active) {
            $this->reject([
                'product_variant_id' => ['Product variant is not available.'],
            ]);
        }

        $product = $variant->product;

        if ($product === null) {
            $this->reject([
                'product_variant_id' => ['Product variant has no parent product.'],
            ], 'not_found');
        }

        if (filled($productId) && $product->id !== $productId) {
            $this->reject([
                'product_id' => ['Product does not match the selected variant.'],
            ]);
        }

        $this->assertProductLifecycleEligible($product);

        // Variant selection is rejected only when the product is already a valid Simple sell path.
        if (
            $this->purchasabilityPolicy->resolvePath($product) === PurchasabilityPath::Simple
            && $this->purchasabilityPolicy->isPurchasable($product)
        ) {
            $this->reject([
                'product_variant_id' => ['This product is sold as a simple product and does not accept a variant selection.'],
            ]);
        }

        $priced = $this->commercePricingResolver->resolveCommerceUnitPrice(
            $product,
            $variant,
            new CommercePricingContext(
                currency: $currency,
                quantity: $pricingQuantity,
                allowLegacyVariantFallback: true,
            ),
        );

        if (! $priced->resolved || (float) $priced->unitPrice <= 0) {
            $this->reject([
                'product_variant_id' => ['No active retail price found for this variant.'],
            ]);
        }

        $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
        $available = $stock->quantityAvailable;

        if (! $stock->resolved || $available < $quantity) {
            $this->reject([
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
        int $pricingQuantity,
    ): array {
        $product = Product::query()
            ->with(['commerceChannel', 'inventory', 'variants.prices', 'variants.inventories'])
            ->find($productId);

        if ($product === null) {
            $this->reject([
                'product_id' => ['Product not found.'],
            ], 'not_found');
        }

        $this->assertProductPurchasable($product);

        if ($this->purchasabilityPolicy->resolvePath($product) !== PurchasabilityPath::Simple) {
            $this->reject([
                'product_variant_id' => ['A product variant is required for this product.'],
            ]);
        }

        $priced = $this->commercePricingResolver->resolveCommerceUnitPrice(
            $product,
            null,
            new CommercePricingContext(
                currency: $currency,
                quantity: $pricingQuantity,
                allowLegacyVariantFallback: true,
            ),
        );

        if (! $priced->resolved || (float) $priced->unitPrice <= 0) {
            $this->reject([
                'product_id' => ['No valid base price found for this product.'],
            ]);
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        $available = $stock->quantityAvailable;

        if (! $stock->resolved || $available < $quantity) {
            $this->reject([
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
            $this->reject([
                'product_id' => ['Product is not available.'],
            ]);
        }
    }

    private function assertProductLifecycleEligible(Product $product): void
    {
        if (! $this->purchasabilityPolicy->isLifecycleEligible($product)) {
            $this->reject([
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
            $this->reject([
                'shipping_method' => ['Shipping method is not required for this product.'],
            ]);
        }

        $method = ShippingMethod::tryFrom($shippingMethod);

        if ($method === null) {
            $this->reject([
                'shipping_method' => ['Invalid shipping method selected.'],
            ]);
        }

        $shippingPrice = $product->shippingPriceForMethod($method->value);

        if ($shippingPrice === null) {
            $this->reject([
                'shipping_method' => ['Selected shipping method is not available for this product.'],
            ]);
        }

        return [$method, $shippingPrice];
    }

    /**
     * Domain failure — same field errors + HTTP 422 as before; Contract v1 code is additive.
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
