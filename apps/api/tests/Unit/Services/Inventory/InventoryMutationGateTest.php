<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryMutationKind;
use App\Models\Inventory;
use App\Models\InventoryStockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\InventoryMutationContext;
use App\Services\Inventory\InventoryMutationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2A-3B-2 — InventoryMutationGate foundation (ADR 055).
 */
class InventoryMutationGateTest extends TestCase
{
    use RefreshDatabase;

    private InventoryMutationGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = app(InventoryMutationGate::class);
    }

    public function test_receive_increases_variant_on_hand_and_writes_ledger(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 10);

        $result = $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Receive,
            quantityChange: 5,
            reason: 'Goods received',
        );

        $this->assertTrue($result->applied);
        $this->assertSame(10, $result->quantityBefore);
        $this->assertSame(5, $result->quantityChange);
        $this->assertSame(15, $result->quantityAfter);
        $this->assertSame(15, (int) $inventory->fresh()->on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $inventory->id,
            'movement_type' => InventoryMovementType::Receive->value,
            'quantity_change' => 5,
            'quantity_after' => 15,
        ]);
    }

    public function test_adjust_changes_on_hand(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 20);

        $result = $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Adjust,
            quantityChange: -3,
            reason: 'Cycle count',
        );

        $this->assertSame(17, $result->quantityAfter);
        $this->assertSame(InventoryMovementType::Adjustment->value, $result->meta['movement_type']);
    }

    public function test_damage_moves_sellable_to_damaged_bucket(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 12, damaged: 1);

        $result = $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Damage,
            quantityChange: -4,
            reason: 'Broken units',
            damagedDelta: 4,
        );

        $fresh = $inventory->fresh();
        $this->assertSame(8, (int) $fresh->on_hand);
        $this->assertSame(5, (int) $fresh->damaged);
        $this->assertSame(InventoryMovementType::Damage->value, $result->meta['movement_type']);
    }

    public function test_return_restocks_sellable(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 5);

        $result = $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Return,
            quantityChange: 2,
            reason: 'Sellable return restock',
        );

        $this->assertSame(7, $result->quantityAfter);
        $this->assertSame(InventoryMovementType::Return->value, $result->meta['movement_type']);
    }

    public function test_sell_decrements_on_hand(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 9);

        $result = $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -2,
            reason: 'POS / order sale',
        );

        $this->assertSame(7, $result->quantityAfter);
        $this->assertSame(InventoryMovementType::Sale->value, $result->meta['movement_type']);
    }

    public function test_sell_rejects_insufficient_stock(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 1);

        $this->expectException(ValidationException::class);

        $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -5,
            reason: 'POS / order sale',
        );
    }

    public function test_apply_context_routes_variant_inventory(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 4);

        $result = $this->gate->apply(new InventoryMutationContext(
            kind: InventoryMutationKind::Receive,
            quantityChange: 6,
            inventory: $inventory,
            reason: 'Receive via context',
        ));

        $this->assertSame(10, $result->quantityAfter);
        $this->assertSame('variant_inventories', $result->source);
    }

    public function test_simple_path_mutates_legacy_inventory_and_movement(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $inventory = Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 8,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        $result = $this->gate->mutateSimple(
            inventory: $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -3,
            reason: 'Simple sale',
        );

        $this->assertSame(5, $result->quantityAfter);
        $this->assertSame('inventory', $result->source);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'quantity' => -3,
            'type' => 'sale',
        ]);
    }

    public function test_transaction_rolls_back_on_failure(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 2);

        try {
            DB::transaction(function () use ($inventory) {
                $this->gate->mutateVariantSellable(
                    inventory: $inventory,
                    kind: InventoryMutationKind::Sell,
                    quantityChange: -1,
                    reason: 'Will rollback',
                );

                throw ValidationException::withMessages(['x' => ['force fail']]);
            });
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(2, (int) $inventory->fresh()->on_hand);
        $this->assertSame(0, InventoryStockMovement::query()->where('variant_inventory_id', $inventory->id)->count());
    }

    public function test_idempotent_replay_with_key(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 10);

        $first = $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -1,
            reason: 'POS / order sale',
            referenceType: 'pos_sale',
            referenceId: 'sale-1',
            idempotencyKey: 'pos-sale-1-line-1',
        );

        $second = $this->gate->mutateVariantSellable(
            inventory: $inventory->fresh() ?? $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -1,
            reason: 'POS / order sale',
            referenceType: 'pos_sale',
            referenceId: 'sale-1',
            idempotencyKey: 'pos-sale-1-line-1',
        );

        $this->assertFalse($first->idempotentReplay);
        $this->assertTrue($second->idempotentReplay);
        $this->assertSame(9, (int) $inventory->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()->where('variant_inventory_id', $inventory->id)->count());
    }

    public function test_lock_for_update_serializes_concurrent_sells_in_sequence(): void
    {
        $inventory = $this->makeVariantInventory(onHand: 5);

        $this->gate->mutateVariantSellable(
            inventory: $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -2,
            reason: 'POS / order sale',
        );
        $this->gate->mutateVariantSellable(
            inventory: $inventory->fresh() ?? $inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -2,
            reason: 'POS / order sale',
        );

        $this->assertSame(1, (int) $inventory->fresh()->on_hand);
    }

    private function makeVariantInventory(int $onHand, int $damaged = 0): VariantInventory
    {
        $product = Product::factory()->create(['price' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        return VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => 0,
            'damaged' => $damaged,
            'inspection' => 0,
            'is_active' => true,
        ]);
    }
}
