<?php

namespace Tests\Unit\Services\Cart;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Cart\ResolveCartPurchasable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResolveCartPurchasablePricingQuantityTest extends TestCase
{
    public function test_pricing_quantity_can_differ_from_line_stock_quantity(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->makeVariant(10000, 6);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => 8000,
        ]);

        $resolved = app(ResolveCartPurchasable::class)->handle(
            $product->id,
            $variant->id,
            6,
            'TZS',
            null,
            10,
        );

        $this->assertSame('8000.00', $resolved['unit_price']);
    }

    public function test_line_stock_is_not_aggregated_when_pricing_quantity_is_high(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->makeVariant(10000, 3);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => 8000,
        ]);

        try {
            app(ResolveCartPurchasable::class)->handle(
                $product->id,
                $variant->id,
                10,
                'TZS',
                null,
                10,
            );
            $this->fail('Expected stock validation to reject line quantity 10.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function makeVariant(float $retail, int $onHand): array
    {
        $product = Product::factory()->tzLocal()->create([
            'price' => 0,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $retail,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        return ['product' => $product, 'variant' => $variant];
    }
}
