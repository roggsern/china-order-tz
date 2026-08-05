<?php

namespace App\Services\Orders;

use App\Enums\ShippingMethod;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Services\Shipping\ShippingDurationResolver;
use App\Support\Catalog\ProductConditionResolver;

/**
 * Order Snapshot Engine — captures immutable commercial data at checkout.
 * After checkout, catalog/shipping edits must never affect these values.
 */
class OrderSnapshotEngine
{
    public function __construct(
        private readonly OrderSnapshotResolver $snapshotResolver,
        private readonly ShippingDurationResolver $durationResolver,
    ) {}

    /**
     * Build an order_items create payload from a cart line.
     *
     * @return array<string, mixed>
     */
    public function snapshotFromCartItem(CartItem $item, ?string $fallbackCurrency = null): array
    {
        $item->loadMissing([
            'product.brand',
            'product.catalogProductType',
            'product.images',
            'product.media',
            'product.shippingOptions',
            'product.supplier',
            'variant.attributeValues.attribute',
            'variant.catalogAttributeValues.attribute',
            'variant.catalogAttributeValues.option',
            'variant.media',
            'variant.product',
        ]);

        $product = $item->product;
        $variant = $item->variant;
        $resolved = $this->snapshotResolver->resolveLine($product, $variant);

        $currency = strtoupper((string) ($item->currency ?: $fallbackCurrency ?: 'TZS'));
        $unitPrice = (string) ($item->price_snapshot ?? $item->unit_price ?? '0.00');
        $quantity = (int) $item->quantity;
        $lineTotal = bcmul($unitPrice, (string) $quantity, 2);

        $shipping = $this->resolveShippingSnapshot($item, $product);

        return $this->assemblePayload(
            productId: $item->product_id,
            productVariantId: $item->product_variant_id,
            productName: (string) ($product?->name ?? 'Product'),
            productSlug: $product?->slug,
            brandName: $product?->brand?->name,
            productCondition: $product !== null
                ? ProductConditionResolver::effectiveForProduct($product)?->value
                : null,
            variantName: $resolved['variant_name'],
            variantSku: $resolved['variant_sku'],
            sku: $resolved['sku'],
            barcode: $resolved['barcode'],
            image: $resolved['image'],
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            currency: $currency,
            attributes: $resolved['attributes'],
            shippingMode: $shipping['mode'],
            shippingPrice: $shipping['price'],
            shippingNotes: $shipping['notes'],
            shippingSubtotal: $shipping['subtotal'],
            deliveryStatus: $shipping['delivery_status'],
            estimatedMinDays: $shipping['estimated_min_days'],
            estimatedMaxDays: $shipping['estimated_max_days'],
            estimatedDeliveryDays: $shipping['estimated_delivery_days'],
        );
    }

    /**
     * Snapshot from live catalog (admin manual order create).
     *
     * @return array<string, mixed>
     */
    public function snapshotFromCatalog(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        string $unitPrice,
        string $currency = 'TZS',
        ?string $shippingMode = null,
        ?string $shippingPrice = null,
    ): array {
        $product->loadMissing(['brand', 'catalogProductType', 'images', 'media', 'shippingOptions']);
        $variant?->loadMissing([
            'attributeValues.attribute',
            'catalogAttributeValues.attribute',
            'catalogAttributeValues.option',
            'media',
            'product',
        ]);

        $resolved = $this->snapshotResolver->resolveLine($product, $variant);

        $currency = strtoupper($currency);
        $unitPrice = (string) $unitPrice;
        $lineTotal = bcmul($unitPrice, (string) $quantity, 2);

        $notes = null;
        if ($shippingMode !== null) {
            $notes = $this->shippingNotesForMode($product, $shippingMode);
        }

        $shippingSubtotal = $shippingPrice !== null
            ? bcmul((string) $shippingPrice, (string) $quantity, 2)
            : null;

        $duration = $this->resolveDurationWindow(
            shippingMode: $shippingMode,
            isChina: $product->requiresChinaShipping(),
            cartMin: null,
            cartMax: null,
            cartTypical: null,
        );

        return $this->assemblePayload(
            productId: $product->id,
            productVariantId: $variant?->id,
            productName: (string) $product->name,
            productSlug: $product->slug,
            brandName: $product->brand?->name,
            productCondition: ProductConditionResolver::effectiveForProduct($product)?->value,
            variantName: $resolved['variant_name'],
            variantSku: $resolved['variant_sku'],
            sku: $resolved['sku'],
            barcode: $resolved['barcode'],
            image: $resolved['image'],
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            currency: $currency,
            attributes: $resolved['attributes'],
            shippingMode: $shippingMode,
            shippingPrice: $shippingPrice,
            shippingNotes: $notes,
            shippingSubtotal: $shippingSubtotal,
            deliveryStatus: null,
            estimatedMinDays: $duration['min_days'],
            estimatedMaxDays: $duration['max_days'],
            estimatedDeliveryDays: $duration['typical_days'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assemblePayload(
        ?string $productId,
        ?string $productVariantId,
        string $productName,
        ?string $productSlug,
        ?string $brandName,
        ?string $productCondition,
        ?string $variantName,
        ?string $variantSku,
        ?string $sku,
        ?string $barcode,
        ?string $image,
        int $quantity,
        string $unitPrice,
        string $lineTotal,
        string $currency,
        ?array $attributes,
        ?string $shippingMode,
        ?string $shippingPrice,
        ?string $shippingNotes,
        ?string $shippingSubtotal,
        ?string $deliveryStatus,
        ?int $estimatedMinDays,
        ?int $estimatedMaxDays,
        ?int $estimatedDeliveryDays,
    ): array {
        return [
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'product_name_snapshot' => $productName,
            'product_slug_snapshot' => $productSlug,
            'sku_snapshot' => $sku,
            'brand_name_snapshot' => $brandName,
            'product_condition_snapshot' => $productCondition,
            'variant_name_snapshot' => $variantName,
            'variant_sku_snapshot' => $variantSku,
            'barcode_snapshot' => $barcode,
            'currency_snapshot' => $currency,
            'unit_price_snapshot' => $unitPrice,
            'shipping_mode_snapshot' => $shippingMode,
            'shipping_price_snapshot' => $shippingPrice,
            'shipping_notes_snapshot' => $shippingNotes,
            'estimated_min_days_snapshot' => $estimatedMinDays,
            'estimated_max_days_snapshot' => $estimatedMaxDays,
            'attributes_snapshot' => $attributes,
            'product_image_snapshot' => $image,
            'image_snapshot' => $image,
            // Legacy columns kept in sync for older readers.
            'product_name' => $productName,
            'variant_name' => $variantName,
            'sku' => $sku ?? '',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'total_price' => $lineTotal,
            'currency' => $currency,
            'shipping_method' => $shippingMode,
            'shipping_price' => $shippingPrice,
            'shipping_subtotal' => $shippingSubtotal,
            'estimated_delivery_days' => $estimatedDeliveryDays,
            'delivery_status' => $deliveryStatus,
        ];
    }

    /**
     * @return array{
     *     mode: string|null,
     *     price: string|null,
     *     notes: string|null,
     *     subtotal: string|null,
     *     delivery_status: string|null,
     *     estimated_min_days: int|null,
     *     estimated_max_days: int|null,
     *     estimated_delivery_days: int|null
     * }
     */
    private function resolveShippingSnapshot(CartItem $item, ?Product $product): array
    {
        if ($product === null) {
            return [
                'mode' => null,
                'price' => null,
                'notes' => null,
                'subtotal' => null,
                'delivery_status' => null,
                'estimated_min_days' => null,
                'estimated_max_days' => null,
                'estimated_delivery_days' => null,
            ];
        }

        if (! $product->requiresChinaShipping()) {
            $duration = $this->resolveDurationWindow(
                shippingMode: null,
                isChina: false,
                cartMin: $item->estimated_min_days !== null ? (int) $item->estimated_min_days : null,
                cartMax: $item->estimated_max_days !== null ? (int) $item->estimated_max_days : null,
                cartTypical: $item->estimated_delivery_days !== null ? (int) $item->estimated_delivery_days : null,
            );

            return [
                'mode' => null,
                'price' => null,
                'notes' => null,
                'subtotal' => null,
                'delivery_status' => 'To Be Negotiated',
                'estimated_min_days' => $duration['min_days'],
                'estimated_max_days' => $duration['max_days'],
                'estimated_delivery_days' => $duration['typical_days'],
            ];
        }

        $mode = $item->shipping_method instanceof ShippingMethod
            ? $item->shipping_method->value
            : ($item->shipping_method !== null ? (string) $item->shipping_method : null);

        $price = $item->shipping_price !== null ? (string) $item->shipping_price : null;
        if ($price === null && $mode !== null) {
            $price = $product->shippingPriceForMethod($mode);
        }

        $notes = $mode !== null ? $this->shippingNotesForMode($product, $mode) : null;
        $subtotal = $price !== null
            ? bcmul($price, (string) $item->quantity, 2)
            : null;

        $duration = $this->resolveDurationWindow(
            shippingMode: $mode,
            isChina: true,
            cartMin: $item->estimated_min_days !== null ? (int) $item->estimated_min_days : null,
            cartMax: $item->estimated_max_days !== null ? (int) $item->estimated_max_days : null,
            cartTypical: $item->estimated_delivery_days !== null ? (int) $item->estimated_delivery_days : null,
        );

        return [
            'mode' => $mode,
            'price' => $price,
            'notes' => $notes,
            'subtotal' => $subtotal,
            'delivery_status' => null,
            'estimated_min_days' => $duration['min_days'],
            'estimated_max_days' => $duration['max_days'],
            'estimated_delivery_days' => $duration['typical_days'],
        ];
    }

    /**
     * Prefer cart-captured windows; otherwise resolve from selected mode / local default.
     *
     * @return array{min_days: int|null, max_days: int|null, typical_days: int|null}
     */
    private function resolveDurationWindow(
        ?string $shippingMode,
        bool $isChina,
        ?int $cartMin,
        ?int $cartMax,
        ?int $cartTypical,
    ): array {
        if ($cartMin !== null && $cartMax !== null) {
            return [
                'min_days' => $cartMin,
                'max_days' => $cartMax,
                'typical_days' => $cartTypical ?? (int) round(($cartMin + $cartMax) / 2),
            ];
        }

        $resolved = $this->durationResolver->resolveForShippingMode($shippingMode);
        if ($resolved === null && ! $isChina) {
            $resolved = $this->durationResolver->resolveLocal();
        }

        if ($resolved === null) {
            return [
                'min_days' => null,
                'max_days' => null,
                'typical_days' => null,
            ];
        }

        return [
            'min_days' => $resolved['min_days'],
            'max_days' => $resolved['max_days'],
            'typical_days' => $resolved['typical_days'],
        ];
    }

    private function shippingNotesForMode(Product $product, string $mode): ?string
    {
        if ($product->relationLoaded('shippingOptions')) {
            $option = $product->shippingOptions->first(function (ProductShippingOption $option) use ($mode): bool {
                $value = $option->transport_mode instanceof ShippingMethod
                    ? $option->transport_mode->value
                    : (string) $option->transport_mode;

                return $value === $mode && $option->is_available;
            });

            return $option?->notes;
        }

        return ProductShippingOption::query()
            ->where('product_id', $product->id)
            ->available()
            ->where('transport_mode', $mode)
            ->value('notes');
    }
}
