<?php

namespace App\Actions\AdminProductVariants\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;

trait ResolvesVariantDefaults
{
    protected function clearOtherDefaults(Product $product, ?string $exceptVariantId = null): void
    {
        $query = ProductVariant::query()->where('product_id', $product->id);

        if ($exceptVariantId !== null) {
            $query->where('id', '!=', $exceptVariantId);
        }

        $query->update(['is_default' => false]);
    }

    protected function ensureSingleDefault(Product $product): void
    {
        $hasDefault = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_default', true)
            ->exists();

        if ($hasDefault) {
            return;
        }

        $first = ProductVariant::query()
            ->where('product_id', $product->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->first();

        $first?->update(['is_default' => true]);
    }

    protected function resolveIsActive(?string $status, mixed $isActive, bool $fallback = true): bool
    {
        if (is_string($status)) {
            return strtolower($status) !== 'inactive';
        }

        if (is_bool($isActive)) {
            return $isActive;
        }

        return $fallback;
    }
}
