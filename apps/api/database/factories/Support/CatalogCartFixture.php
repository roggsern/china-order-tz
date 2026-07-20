<?php

namespace Database\Factories\Support;

use App\Enums\VariantPriceType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;

final class CatalogCartFixture
{
    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    public static function purchasable(
        float $retailPrice = 25000,
        int $onHand = 50,
        string $currency = 'TZS',
    ): array {
        $product = Product::factory()->create([
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
}
