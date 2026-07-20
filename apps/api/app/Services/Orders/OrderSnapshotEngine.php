<?php

namespace App\Services\Orders;

use App\Enums\ShippingMethod;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;

/**
 * Order Snapshot Engine — captures immutable commercial data at checkout.
 * After checkout, catalog/shipping edits must never affect these values.
 */
class OrderSnapshotEngine
{
    /**
     * Build an order_items create payload from a cart line.
     *
     * @return array<string, mixed>
     */
    public function snapshotFromCartItem(CartItem $item, ?string $fallbackCurrency = null): array
    {
        $item->loadMissing([
            'product.brand',
            'product.images',
            'product.shippingOptions',
            'product.supplier',
            'variant.attributeValues.attribute',
        ]);

        $product = $item->product;
        $variant = $item->variant;

        $currency = strtoupper((string) ($item->currency ?: $fallbackCurrency ?: 'TZS'));
        $unitPrice = (string) ($item->price_snapshot ?? $item->unit_price ?? '0.00');
        $quantity = (int) $item->quantity;
        $lineTotal = bcmul($unitPrice, (string) $quantity, 2);

        $productName = (string) ($product?->name ?? 'Product');
        $productSlug = $product?->slug;
        $brandName = $product?->brand?->name;
        $variantName = $variant?->name;
        $variantSku = $variant?->sku;
        $sku = $variantSku ?? $product?->sku;
        $image = $this->resolveImagePath($product);

        $shipping = $this->resolveShippingSnapshot($item, $product);

        return $this->assemblePayload(
            productId: $item->product_id,
            productVariantId: $item->product_variant_id,
            productName: $productName,
            productSlug: $productSlug,
            brandName: $brandName,
            variantName: $variantName,
            variantSku: $variantSku,
            sku: $sku,
            image: $image,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            currency: $currency,
            attributes: $this->resolveAttributes($variant),
            shippingMode: $shipping['mode'],
            shippingPrice: $shipping['price'],
            shippingNotes: $shipping['notes'],
            shippingSubtotal: $shipping['subtotal'],
            deliveryStatus: $shipping['delivery_status'],
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
        $product->loadMissing(['brand', 'images', 'shippingOptions']);
        $variant?->loadMissing(['attributeValues.attribute']);

        $currency = strtoupper($currency);
        $unitPrice = (string) $unitPrice;
        $lineTotal = bcmul($unitPrice, (string) $quantity, 2);
        $variantSku = $variant?->sku;
        $sku = $variantSku ?? $product->sku;
        $image = $this->resolveImagePath($product);

        $notes = null;
        if ($shippingMode !== null) {
            $notes = $this->shippingNotesForMode($product, $shippingMode);
        }

        $shippingSubtotal = $shippingPrice !== null
            ? bcmul((string) $shippingPrice, (string) $quantity, 2)
            : null;

        return $this->assemblePayload(
            productId: $product->id,
            productVariantId: $variant?->id,
            productName: (string) $product->name,
            productSlug: $product->slug,
            brandName: $product->brand?->name,
            variantName: $variant?->name,
            variantSku: $variantSku,
            sku: $sku,
            image: $image,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            currency: $currency,
            attributes: $this->resolveAttributes($variant),
            shippingMode: $shippingMode,
            shippingPrice: $shippingPrice,
            shippingNotes: $notes,
            shippingSubtotal: $shippingSubtotal,
            deliveryStatus: null,
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
        ?string $variantName,
        ?string $variantSku,
        ?string $sku,
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
    ): array {
        return [
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'product_name_snapshot' => $productName,
            'product_slug_snapshot' => $productSlug,
            'sku_snapshot' => $sku,
            'brand_name_snapshot' => $brandName,
            'variant_name_snapshot' => $variantName,
            'variant_sku_snapshot' => $variantSku,
            'currency_snapshot' => $currency,
            'unit_price_snapshot' => $unitPrice,
            'shipping_mode_snapshot' => $shippingMode,
            'shipping_price_snapshot' => $shippingPrice,
            'shipping_notes_snapshot' => $shippingNotes,
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
            'delivery_status' => $deliveryStatus,
        ];
    }

    /**
     * @return array{
     *     mode: string|null,
     *     price: string|null,
     *     notes: string|null,
     *     subtotal: string|null,
     *     delivery_status: string|null
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
            ];
        }

        if (! $product->requiresChinaShipping()) {
            return [
                'mode' => null,
                'price' => null,
                'notes' => null,
                'subtotal' => null,
                'delivery_status' => 'To Be Negotiated',
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

        return [
            'mode' => $mode,
            'price' => $price,
            'notes' => $notes,
            'subtotal' => $subtotal,
            'delivery_status' => null,
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

    /**
     * @return list<array{attribute: string, value: string}>|null
     */
    private function resolveAttributes(?ProductVariant $variant): ?array
    {
        if ($variant === null) {
            return null;
        }

        if (! $variant->relationLoaded('attributeValues')) {
            $variant->loadMissing('attributeValues.attribute');
        }

        $rows = $variant->attributeValues
            ->map(fn ($value) => [
                'attribute' => (string) ($value->attribute?->name ?? $value->attribute?->slug ?? 'Attribute'),
                'value' => (string) ($value->value ?? ''),
            ])
            ->filter(fn (array $row) => $row['value'] !== '')
            ->values()
            ->all();

        return $rows === [] ? null : $rows;
    }

    private function resolveImagePath(?Product $product): ?string
    {
        if ($product === null) {
            return null;
        }

        $images = $product->relationLoaded('images')
            ? $product->images
            : $product->images()->orderBy('sort_order')->get();

        if ($images->isEmpty()) {
            return null;
        }

        /** @var ProductImage|null $primary */
        $primary = $images->firstWhere('is_primary', true) ?? $images->sortBy('sort_order')->first();

        return $primary?->path ?? $primary?->url ?? null;
    }
}
