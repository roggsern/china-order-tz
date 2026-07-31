<?php

namespace Database\Factories\Support;

use App\Enums\VariantPriceType;
use App\Models\ChinaCommercialStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;

final class CatalogCartFixture
{
    /**
     * TZ_LOCAL sellable variant with MAIN warehouse inventory.
     *
     * @return array{product: Product, variant: ProductVariant}
     */
    public static function purchasable(
        float $retailPrice = 25000,
        int $onHand = 50,
        string $currency = 'TZS',
    ): array {
        $product = Product::factory()->tzLocal()->create([
            'is_active' => true,
            'lifecycle_status' => 'active',
            'is_demo' => false,
            'price' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_default' => true,
            'price' => null,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => $currency,
            'amount' => $retailPrice,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => 0,
            'reorder_level' => 5,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        return ['product' => $product, 'variant' => $variant];
    }

    /**
     * CHINA_IMPORT sellable variant with commercial availability (no MAIN commit path).
     *
     * @return array{product: Product, variant: ProductVariant}
     */
    public static function chinaPurchasable(
        float $retailPrice = 25000,
        int $available = 50,
        string $currency = 'TZS',
    ): array {
        $product = Product::factory()->fromChina()->create([
            'is_active' => true,
            'lifecycle_status' => 'active',
            'is_demo' => false,
            'price' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_default' => true,
            'price' => null,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => $currency,
            'amount' => $retailPrice,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_quantity' => $available,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        return ['product' => $product->fresh(['commerceChannel']), 'variant' => $variant];
    }
}
