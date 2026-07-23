<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryDisposition;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ReturnRequestStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\InventoryStockMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Inventory\ReturnInventoryRestorationService;
use App\Services\Returns\ReturnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADR-055 Phase F / RC1-G3 — Online return inventory restoration at Completed.
 */
class ReturnInventoryRestorationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     user: User,
     *     order: Order,
     *     item: OrderItem,
     *     inventory: VariantInventory,
     *     product: Product,
     *     variant: ProductVariant
     * }
     */
    private function deliveredVariantOrder(int $onHand, int $orderQty, int $consumedOnHand): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Return Restock Product',
            'price' => 0,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'name' => 'Size M',
            'sku' => 'RET-VAR-'.uniqid(),
        ]);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand - $consumedOnHand,
            'reserved' => 0,
            'damaged' => 0,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDays(5),
            'subtotal' => 25000 * $orderQty,
            'total' => 25000 * $orderQty,
            'currency' => 'TZS',
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => $orderQty,
            'unit_price' => 25000,
            'unit_price_snapshot' => 25000,
            'line_total' => 25000 * $orderQty,
            'total_price' => 25000 * $orderQty,
            'currency' => 'TZS',
        ]);

        Fulfillment::factory()->create(['order_id' => $order->id]);
        Shipment::factory()->create([
            'order_id' => $order->id,
            'status' => ShipmentLifecycleStatus::Delivered,
            'delivered_at' => now()->subDay(),
        ]);

        return compact('user', 'order', 'item', 'inventory', 'product', 'variant');
    }

    private function createReturnThroughApi(User $user, Order $order, OrderItem $item, int $qty): string
    {
        Sanctum::actingAs($user);

        return $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'RC1-G3 return',
            'items' => [['order_item_id' => $item->id, 'quantity' => $qty]],
        ])->assertCreated()->json('data.id');
    }

    private function advanceToInspection(string $returnId, Admin $admin): void
    {
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", ['status' => 'inspection'])->assertOk();
    }

    private function completeWithDisposition(
        string $returnId,
        string $returnItemId,
        string $disposition,
        ?float $refundAmount = 20000,
    ): \Illuminate\Testing\TestResponse {
        return $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'completed',
            'items' => [[
                'id' => $returnItemId,
                'condition' => 'opened',
                'resolution' => 'refund',
                'refund_amount' => $refundAmount,
                'inventory_disposition' => $disposition,
            ]],
        ]);
    }

    public function test_sellable_online_return_restores_on_hand_once_with_ledger(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 10, orderQty: 2, consumedOnHand: 2);
        $this->assertSame(8, (int) $fx['inventory']->fresh()->on_hand);

        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 2);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $this->assertSame(8, (int) $fx['inventory']->fresh()->on_hand);

        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Sellable->value)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.items.0.inventory_disposition', 'sellable');

        $this->assertSame(10, (int) $fx['inventory']->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('variant_inventory_id', $fx['inventory']->id)
            ->where('movement_type', InventoryMovementType::Return->value)
            ->where('reference_type', ReturnItem::class)
            ->where('reference_id', $returnItemId)
            ->where('quantity_change', 2)
            ->count());
    }

    public function test_damaged_online_return_increases_damaged_not_sellable(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 5, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $before = $fx['inventory']->fresh();
        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Damaged->value)
            ->assertOk();

        $after = $fx['inventory']->fresh();
        $this->assertSame((int) $before->on_hand, (int) $after->on_hand);
        $this->assertSame((int) $before->damaged + 1, (int) $after->damaged);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('variant_inventory_id', $fx['inventory']->id)
            ->where('movement_type', InventoryMovementType::Damage->value)
            ->where('reference_id', $returnItemId)
            ->count());
    }

    public function test_no_restock_completes_without_stock_mutation(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 6, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $beforeOnHand = (int) $fx['inventory']->fresh()->on_hand;
        $beforeDamaged = (int) $fx['inventory']->fresh()->damaged;
        $movementsBefore = InventoryStockMovement::query()->where('variant_inventory_id', $fx['inventory']->id)->count();

        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::NoRestock->value)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame($beforeOnHand, (int) $fx['inventory']->fresh()->on_hand);
        $this->assertSame($beforeDamaged, (int) $fx['inventory']->fresh()->damaged);
        $this->assertSame(
            $movementsBefore,
            InventoryStockMovement::query()->where('variant_inventory_id', $fx['inventory']->id)->count(),
        );
    }

    public function test_missing_disposition_rejects_completion_without_side_effects(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 4, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $before = (int) $fx['inventory']->fresh()->on_hand;

        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'completed',
            'items' => [[
                'id' => $returnItemId,
                'resolution' => 'refund',
                'refund_amount' => 10000,
            ]],
        ])->assertStatus(422);

        $this->assertSame(ReturnRequestStatus::Inspection, ReturnRequest::query()->findOrFail($returnId)->status);
        $this->assertSame($before, (int) $fx['inventory']->fresh()->on_hand);
    }

    public function test_rejected_return_does_not_mutate_inventory(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 4, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        Sanctum::actingAs($admin);

        $before = (int) $fx['inventory']->fresh()->on_hand;
        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", ['status' => 'rejected'])->assertOk();
        $this->assertSame($before, (int) $fx['inventory']->fresh()->on_hand);
        $this->assertSame(0, InventoryStockMovement::query()
            ->where('variant_inventory_id', $fx['inventory']->id)
            ->whereIn('movement_type', [
                InventoryMovementType::Return->value,
                InventoryMovementType::Damage->value,
            ])
            ->count());
    }

    public function test_idempotent_completion_and_service_replay(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 9, orderQty: 2, consumedOnHand: 2);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 2);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Sellable->value)->assertOk();
        $this->assertSame(9, (int) $fx['inventory']->fresh()->on_hand);

        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Sellable->value)->assertOk();
        $this->assertSame(9, (int) $fx['inventory']->fresh()->on_hand);

        $return = ReturnRequest::query()->with('items')->findOrFail($returnId);
        app(ReturnInventoryRestorationService::class)->restoreForCompletedReturn($return, $admin);
        $this->assertSame(9, (int) $fx['inventory']->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('reference_id', $returnItemId)
            ->where('movement_type', InventoryMovementType::Return->value)
            ->count());
    }

    public function test_concurrent_completion_attempts_restore_once(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 5, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = (string) ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $engine = app(ReturnEngine::class);
        $return = ReturnRequest::query()->findOrFail($returnId);
        $payload = [
            'status' => 'completed',
            'items' => [[
                'id' => $returnItemId,
                'resolution' => 'refund',
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ];

        $engine->updateStatus($return, $payload, $admin);
        $engine->updateStatus($return->fresh() ?? $return, $payload, $admin);

        $this->assertSame(5, (int) $fx['inventory']->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('reference_id', $returnItemId)
            ->where('movement_type', InventoryMovementType::Return->value)
            ->count());
    }

    public function test_partial_return_restores_only_returned_quantity(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 10, orderQty: 5, consumedOnHand: 5);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 2);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Sellable->value)->assertOk();
        $this->assertSame(7, (int) $fx['inventory']->fresh()->on_hand);
        $this->assertSame(2, (int) InventoryStockMovement::query()
            ->where('reference_id', $returnItemId)
            ->where('movement_type', InventoryMovementType::Return->value)
            ->value('quantity_change'));
    }

    public function test_multiple_return_items_get_independent_mutations(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
        ]);
        $v1 = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'sku' => 'M1']);
        $v2 = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'sku' => 'M2']);
        $inv1 = VariantInventory::query()->create([
            'product_variant_id' => $v1->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 3,
            'reserved' => 0,
            'damaged' => 0,
            'is_active' => true,
        ]);
        $inv2 = VariantInventory::query()->create([
            'product_variant_id' => $v2->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'damaged' => 0,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDay(),
        ]);
        $oi1 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $v1->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'unit_price_snapshot' => 10000,
        ]);
        $oi2 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $v2->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'unit_price_snapshot' => 10000,
        ]);
        Fulfillment::factory()->create(['order_id' => $order->id]);
        Shipment::factory()->create([
            'order_id' => $order->id,
            'status' => ShipmentLifecycleStatus::Delivered,
            'delivered_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);
        $returnId = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Multi line',
            'items' => [
                ['order_item_id' => $oi1->id, 'quantity' => 1],
                ['order_item_id' => $oi2->id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.id');

        $admin = Admin::factory()->create();
        $this->advanceToInspection($returnId, $admin);
        $items = ReturnItem::query()->where('return_request_id', $returnId)->orderBy('id')->get();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'completed',
            'items' => [
                [
                    'id' => $items[0]->id,
                    'resolution' => 'refund',
                    'inventory_disposition' => InventoryDisposition::Sellable->value,
                ],
                [
                    'id' => $items[1]->id,
                    'resolution' => 'refund',
                    'inventory_disposition' => InventoryDisposition::Damaged->value,
                ],
            ],
        ])->assertOk();

        $this->assertSame(4, (int) $inv1->fresh()->on_hand);
        $this->assertSame(4, (int) $inv2->fresh()->on_hand);
        $this->assertSame(1, (int) $inv2->fresh()->damaged);
        $this->assertSame(1, InventoryStockMovement::query()->where('reference_id', $items[0]->id)->count());
        $this->assertSame(1, InventoryStockMovement::query()->where('reference_id', $items[1]->id)->count());
    }

    public function test_transaction_rollback_keeps_status_and_inventory(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 4, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = (string) ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $this->mock(ReturnInventoryRestorationService::class, function ($mock) {
            $mock->shouldReceive('assertItemsReadyForCompletion')->once();
            $mock->shouldReceive('restoreForCompletedReturn')
                ->once()
                ->andThrow(new \RuntimeException('simulated inventory failure'));
        });
        $this->app->forgetInstance(ReturnEngine::class);

        $before = (int) $fx['inventory']->fresh()->on_hand;
        $engine = app(ReturnEngine::class);

        try {
            $engine->updateStatus(
                ReturnRequest::query()->findOrFail($returnId),
                [
                    'status' => 'completed',
                    'items' => [[
                        'id' => $returnItemId,
                        'resolution' => 'refund',
                        'inventory_disposition' => InventoryDisposition::Sellable->value,
                    ]],
                ],
                $admin,
            );
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated inventory failure', $e->getMessage());
        }

        $this->assertSame(ReturnRequestStatus::Inspection, ReturnRequest::query()->findOrFail($returnId)->status);
        $this->assertSame($before, (int) $fx['inventory']->fresh()->on_hand);
    }

    public function test_location_resolution_uses_canonical_main(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 3, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Sellable->value)->assertOk();

        $movement = InventoryStockMovement::query()
            ->where('reference_id', $returnItemId)
            ->where('movement_type', InventoryMovementType::Return->value)
            ->firstOrFail();

        $this->assertSame($fx['inventory']->id, $movement->variant_inventory_id);
        $this->assertSame('MAIN', $fx['inventory']->fresh()->warehouse_code);
    }

    public function test_customer_payload_hides_inventory_disposition(): void
    {
        $fx = $this->deliveredVariantOrder(onHand: 3, orderQty: 1, consumedOnHand: 1);
        $admin = Admin::factory()->create();
        $returnId = $this->createReturnThroughApi($fx['user'], $fx['order'], $fx['item'], 1);
        $this->advanceToInspection($returnId, $admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');
        $this->completeWithDisposition($returnId, $returnItemId, InventoryDisposition::Sellable->value)->assertOk();

        Sanctum::actingAs($fx['user']);
        $customer = $this->getJson("/api/v1/returns/{$returnId}")->assertOk()->json('data');
        $encoded = json_encode($customer);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('inventory_disposition', $encoded);
        $this->assertStringNotContainsString('variant_inventory', $encoded);
        $this->assertStringNotContainsString('unit_cost', $encoded);
        $this->assertStringNotContainsString('warehouse_code', $encoded);
    }
}
