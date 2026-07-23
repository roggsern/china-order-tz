<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\StockResolutionContext;
use App\Services\Inventory\StockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2A-3B-1 — StockResolver foundation (ADR 055).
 */
class StockResolverTest extends TestCase
{
    use RefreshDatabase;

    private StockResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(StockResolver::class);
    }

    public function test_simple_product_resolves_inventory_table(): void
    {
        $product = $this->makeSimpleProduct(onHand: 20, reserved: 5);

        $result = $this->resolver->resolveSimpleProduct($product);

        $this->assertTrue($result->resolved);
        $this->assertSame('inventory', $result->source);
        $this->assertSame('simple', $result->inventoryType);
        $this->assertSame(20, $result->quantityOnHand);
        $this->assertSame(5, $result->quantityReserved);
        $this->assertSame(15, $result->quantityAvailable);
        $this->assertInstanceOf(Inventory::class, $result->inventory);
        $this->assertTrue($result->hasInventoryPolicy());
    }

    public function test_simple_product_unresolved_without_inventory_row(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 10000,
        ]);

        $result = $this->resolver->resolveSimpleProduct($product);

        $this->assertFalse($result->resolved);
        $this->assertSame(0, $result->quantityAvailable);
        $this->assertFalse($result->hasInventoryPolicy());
    }

    public function test_variant_product_resolves_main_variant_inventory(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 40,
            'reserved' => 8,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolveVariantProduct($variant->fresh('inventories'), null, $product);

        $this->assertTrue($result->resolved);
        $this->assertSame('variant_inventories', $result->source);
        $this->assertSame('variant', $result->inventoryType);
        $this->assertSame(40, $result->quantityOnHand);
        $this->assertSame(8, $result->quantityReserved);
        $this->assertSame(32, $result->quantityAvailable);
        $this->assertSame('MAIN', $result->location);
        $this->assertInstanceOf(VariantInventory::class, $result->inventory);
    }

    public function test_variant_active_main_uses_canonical(): void
    {
        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 40,
            'reserved' => 8,
            'is_active' => true,
        ]);
        // Legacy row must not win when active MAIN exists.
        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 99,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        $result = $this->resolver->resolveVariantProduct($variant->fresh(['inventories', 'inventory']), null, $product);

        $this->assertTrue($result->resolved);
        $this->assertSame('variant_inventories', $result->source);
        $this->assertSame('variant', $result->inventoryType);
        $this->assertSame(32, $result->quantityAvailable);
        $this->assertFalse($result->meta['legacy_fallback'] ?? false);
    }

    public function test_variant_missing_main_falls_back_to_legacy(): void
    {
        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 9,
            'reserved_quantity' => 2,
            'low_stock_threshold' => 1,
        ]);

        $result = $this->resolver->resolveVariantProduct($variant->fresh(['inventories', 'inventory']), null, $product);

        $this->assertTrue($result->resolved);
        $this->assertSame('inventory', $result->source);
        $this->assertSame('variant_legacy', $result->inventoryType);
        $this->assertSame(7, $result->quantityAvailable);
        $this->assertTrue($result->meta['legacy_fallback'] ?? false);
    }

    public function test_variant_inactive_main_only_is_unavailable(): void
    {
        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 99,
            'reserved' => 0,
            'is_active' => false,
        ]);

        $result = $this->resolver->resolveVariantProduct($variant->fresh('inventories'));

        $this->assertFalse($result->resolved);
        $this->assertSame(0, $result->quantityAvailable);
        $this->assertTrue($result->meta['inactive_canonical'] ?? false);
        $this->assertFalse($this->resolver->hasVariantInventoryPolicy($variant->fresh('inventories')));
    }

    public function test_variant_inactive_main_with_legacy_is_unavailable(): void
    {
        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 50,
            'reserved' => 0,
            'is_active' => false,
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 20,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        $result = $this->resolver->resolveVariantProduct($variant->fresh(['inventories', 'inventory']), null, $product);

        $this->assertFalse($result->resolved);
        $this->assertSame(0, $result->quantityAvailable);
        $this->assertTrue($result->meta['inactive_canonical'] ?? false);
        $this->assertFalse($result->meta['legacy_fallback'] ?? false);
        $this->assertFalse($this->resolver->hasVariantInventoryPolicy($variant->fresh(['inventories', 'inventory'])));
    }

    public function test_variant_ignores_inactive_main_row(): void
    {
        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 99,
            'reserved' => 0,
            'is_active' => false,
        ]);

        $result = $this->resolver->resolveVariantProduct($variant->fresh('inventories'));

        $this->assertFalse($result->resolved);
        $this->assertFalse($this->resolver->hasVariantInventoryPolicy($variant->fresh('inventories')));
    }

    public function test_resolve_delegates_simple_and_variant(): void
    {
        $simple = $this->makeSimpleProduct(onHand: 7, reserved: 0);
        $simpleResult = $this->resolver->resolve($simple, null);
        $this->assertSame('simple', $simpleResult->inventoryType);
        $this->assertSame(7, $simpleResult->quantityAvailable);

        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 3,
            'reserved' => 1,
            'is_active' => true,
        ]);

        $variantResult = $this->resolver->resolve($product, $variant->fresh('inventories'));
        $this->assertSame('variant', $variantResult->inventoryType);
        $this->assertSame(2, $variantResult->quantityAvailable);
    }

    public function test_context_warehouse_code_placeholder_defaults_to_main(): void
    {
        $context = new StockResolutionContext(warehouseCode: 'MAIN');
        $this->assertSame('MAIN', $context->warehouseCode());

        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolveVariantProduct(
            $variant->fresh('inventories'),
            $context,
            $product,
        );

        $this->assertTrue($result->resolved);
        $this->assertSame('MAIN', $result->location);
        $this->assertNull($result->meta['warehouse_allocation']);
        $this->assertFalse($result->meta['reservation_applied']);
    }

    private function makeSimpleProduct(int $onHand, int $reserved): Product
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 12000,
        ]);

        Inventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => $onHand,
                'reserved_quantity' => $reserved,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory']) ?? $product;
    }
}
