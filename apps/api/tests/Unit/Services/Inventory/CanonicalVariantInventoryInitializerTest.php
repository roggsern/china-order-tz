<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\InventoryStockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\Inventory\CanonicalVariantInventoryInitializer;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Inventory\InventoryMutationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RC1-B1 — Canonical MAIN initialization must not shadow positive legacy stock.
 */
class CanonicalVariantInventoryInitializerTest extends TestCase
{
    use RefreshDatabase;

    private CanonicalVariantInventoryInitializer $initializer;

    private AdminInventoryApplicationService $adminInventory;

    private InventoryMutationGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializer = app(CanonicalVariantInventoryInitializer::class);
        $this->adminInventory = app(AdminInventoryApplicationService::class);
        $this->gate = app(InventoryMutationGate::class);
    }

    public function test_legacy_positive_quantity_bootstraps_main_inventory(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(10);

        $main = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'requested_on_hand' => null,
        ]);

        $this->assertSame('MAIN', $main->warehouse_code);
        $this->assertSame(10, (int) $main->fresh()->on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $main->id,
            'movement_type' => InventoryMovementType::Receive->value,
            'quantity_change' => 10,
        ]);
        $this->assertSame(10, (int) Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->value('quantity'));
    }

    public function test_admin_omitted_on_hand_inherits_legacy_quantity(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(9);

        $main = $this->adminInventory->createVariantInventory($variant, [
            'warehouse_code' => 'MAIN',
        ]);

        $this->assertSame(9, (int) $main->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('variant_inventory_id', $main->id)
            ->where('movement_type', InventoryMovementType::Receive->value)
            ->count());
    }

    public function test_explicit_on_hand_does_not_double_add_legacy(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(20);

        $main = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'requested_on_hand' => 7,
        ]);

        $this->assertSame(7, (int) $main->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('variant_inventory_id', $main->id)
            ->where('movement_type', InventoryMovementType::Receive->value)
            ->count());
        $this->assertSame(20, (int) Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->value('quantity'));
    }

    public function test_existing_active_main_zero_is_authoritative(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(15);
        $existing = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 0,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $result = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'requested_on_hand' => null,
        ]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(0, (int) $result->fresh()->on_hand);
        $this->assertSame(0, InventoryStockMovement::query()
            ->where('variant_inventory_id', $existing->id)
            ->count());
    }

    public function test_existing_active_main_positive_is_noop(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(15);
        $existing = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $result = $this->initializer->ensure($variant, ['warehouse_code' => 'MAIN']);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(4, (int) $result->fresh()->on_hand);
        $this->assertSame(0, InventoryStockMovement::query()
            ->where('variant_inventory_id', $existing->id)
            ->count());
    }

    public function test_repeated_initialization_is_idempotent(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(8);

        $first = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'idempotency_key' => 'init-once',
        ]);
        $second = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'idempotency_key' => 'init-once',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(8, (int) $second->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('variant_inventory_id', $first->id)
            ->where('movement_type', InventoryMovementType::Receive->value)
            ->count());
    }

    public function test_trashed_main_inventory_is_restored_and_reinitialized(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(6);
        $trashed = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 0,
            'reserved' => 0,
            'is_active' => true,
        ]);
        $trashed->delete();

        $restored = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'requested_on_hand' => null,
        ]);

        $this->assertFalse($restored->trashed());
        $this->assertSame(6, (int) $restored->fresh()->on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $restored->id,
            'movement_type' => InventoryMovementType::Receive->value,
            'quantity_change' => 6,
        ]);
    }

    public function test_no_legacy_inventory_creates_zero_without_receive_ledger(): void
    {
        $variant = $this->makeBareVariant();

        $main = $this->initializer->ensure($variant, [
            'warehouse_code' => 'MAIN',
            'requested_on_hand' => null,
        ]);

        $this->assertSame(0, (int) $main->fresh()->on_hand);
        $this->assertSame(0, InventoryStockMovement::query()
            ->where('variant_inventory_id', $main->id)
            ->count());
    }

    public function test_transaction_failure_rolls_back_partial_main_creation(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(5);

        try {
            DB::transaction(function () use ($variant): void {
                $this->initializer->ensure($variant, ['warehouse_code' => 'MAIN']);
                throw new \RuntimeException('forced rollback');
            });
            $this->fail('Expected exception');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->count());
        $this->assertSame(0, InventoryStockMovement::query()->count());
        $this->assertSame(5, (int) Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->value('quantity'));
    }

    public function test_inventory_control_bootstrap_uses_canonical_initializer_behavior(): void
    {
        ['variant' => $variant] = $this->makeVariantWithLegacy(11);
        $store = Store::query()->create([
            'code' => 'INIT'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Init Store',
            'slug' => 'init-store-'.uniqid(),
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
        $location = InventoryLocation::query()->create([
            'store_id' => $store->id,
            'code' => 'MAIN',
            'name' => 'Main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $engine = app(InventoryControlEngine::class);
        $inv = $engine->resolveOrCreateInventory($variant, $location, true);

        $this->assertSame(11, (int) $inv->on_hand);
        $this->assertSame((string) $location->id, (string) $inv->inventory_location_id);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $inv->id,
            'movement_type' => InventoryMovementType::Receive->value,
            'quantity_change' => 11,
        ]);
    }

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function makeVariantWithLegacy(int $legacyQty): array
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'sku' => 'CANON-'.uniqid(),
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $legacyQty,
            'reserved_quantity' => 0,
        ]);

        return ['product' => $product, 'variant' => $variant];
    }

    private function makeBareVariant(): ProductVariant
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'is_demo' => false,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'sku' => 'BARE-'.uniqid(),
        ]);
    }
}
