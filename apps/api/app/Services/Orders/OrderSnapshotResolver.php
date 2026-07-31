<?php

namespace App\Services\Orders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Catalog\ProductVariantAttributeResolver;

/**
 * Resolves immutable commercial fields for order line snapshots.
 * Attribute display reuses ProductVariantAttributeResolver (catalog → legacy).
 * Image resolution reuses CustomerProductMediaResolver (variant → product → legacy).
 */
class OrderSnapshotResolver
{
    public function __construct(
        private readonly CustomerProductMediaResolver $mediaResolver,
        private readonly ProductVariantAttributeResolver $attributeResolver,
    ) {}

    /**
     * @return array{
     *   variant_name: string|null,
     *   sku: string|null,
     *   variant_sku: string|null,
     *   barcode: string|null,
     *   attributes: list<array{attribute: string, value: string}>|null,
     *   image: string|null
     * }
     */
    public function resolveLine(?Product $product, ?ProductVariant $variant): array
    {
        if ($variant !== null) {
            $variant->loadMissing([
                'catalogAttributeValues.attribute',
                'catalogAttributeValues.option',
                'attributeValues.attribute',
                'media',
                'product',
            ]);
        }

        if ($product !== null) {
            $product->loadMissing(['media', 'images']);
        }

        $variantSku = $this->resolveVariantSku($variant);
        $sku = $variantSku ?? ($product?->sku !== null ? (string) $product->sku : null);

        return [
            'variant_name' => $this->resolveVariantName($variant),
            'sku' => $sku,
            'variant_sku' => $variantSku,
            'barcode' => $this->resolveBarcode($variant),
            'attributes' => $this->resolveAttributes($variant),
            'image' => $this->resolveImage($product, $variant),
        ];
    }

    public function resolveVariantName(?ProductVariant $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        $name = trim((string) ($variant->name ?? ''));

        return $name !== '' ? $name : null;
    }

    public function resolveVariantSku(?ProductVariant $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        $sku = trim((string) ($variant->sku ?? ''));

        return $sku !== '' ? $sku : null;
    }

    public function resolveBarcode(?ProductVariant $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        $barcode = trim((string) ($variant->barcode ?? ''));

        return $barcode !== '' ? $barcode : null;
    }

    /**
     * @return list<array{attribute: string, value: string}>|null
     */
    public function resolveAttributes(?ProductVariant $variant): ?array
    {
        if ($variant === null) {
            return null;
        }

        $rows = $this->attributeResolver->resolve($variant);

        return $rows === [] ? null : $rows;
    }

    public function resolveImage(?Product $product, ?ProductVariant $variant = null): ?string
    {
        if ($product === null) {
            return null;
        }

        $primary = $this->mediaResolver->resolvePrimary($product, $variant);
        if ($primary === null) {
            return null;
        }

        return $primary['path'] ?? $primary['url'] ?? null;
    }
}
