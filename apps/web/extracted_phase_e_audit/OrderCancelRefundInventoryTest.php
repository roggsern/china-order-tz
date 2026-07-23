<?php

namespace Tests\Unit\Services\Inventory;

use App\Actions\AdminOrders\CancelOrderAction;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\RefundTransactionStatus;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryStockMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Inventory\OrderInventoryRestockService;
use App\Services\Returns\RefundEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2A-3C-3 — Cancel / refund inventory restore via InventoryMutationGate (ADR 055).
 */
class OrderCancelRefundInventoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderInventoryRestockService $restock;

    private CancelOrderAction $cancel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restock = app(OrderInventoryRestockService::class);
        $this->cancel = app(CancelOrderAction::class);
    }

    public function test_cancel_simple_order_restocks_via_gate(): void
    {
        $fixture = $this->makePaidSimpleOrder(onHand: 5, qty: 2);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->cancel->handle($fixture['order'], 'Customer request');

        $this->assertSame(7, (int) $fixture['inventory']->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $fixture['inventory']->id,
            'type' => 'restock',
            'quantity' => 2,
        ]);
        $this->assertSame(
            OrderStatus::RefundPending,
            $fixture['order']->fresh()->status,
        );
    }

    public function test_cancel_variant_order_restocks_via_gate(): void
    {
        $fixture = $this->makePaidVariantOrder(onHand: 4, qty: 3);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->cancel->handle($fixture['order'], 'Out of stock error');

        $inv = $fixture['inventory']->fresh();
        $this->assertSame(7, (int) $inv->on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $inv->id,
            'movement_type' => InventoryMovementType::Return->value,
            'quantity_change' => 3,
        ]);
    }

    public function test_duplicate_cancel_does_not_double_restock(): void
    {
        $fixture = $this->makePaidSimpleOrder(onHand: 10, qty: 4);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->cancel->handle($fixture['order'], 'First cancel');
        $this->assertSame(14, (int) $fixture['inventory']->fresh()->quantity);

        // Lifecycle no-ops when already refund_pending; restock must not run again.
        $this->cancel->handle($fixture['order']->fresh() ?? $fixture['order'], 'Second cancel');
        $this->assertSame(14, (int) $fixture['inventory']->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::query()
            ->where('inventory_id', $fixture['inventory']->id)
            ->where('type', 'restock')
            ->count());
    }

    public function test_refund_pending_cancel_restocks_once(): void
    {
        $fixture = $this->makePaidSimpleOrder(onHand: 6, qty: 2);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $cancelled = $this->cancel->handle($fixture['order'], 'Refund path');
        $this->assertSame(OrderStatus::RefundPending, $cancelled->status);
        $this->assertSame(8, (int) $fixture['inventory']->fresh()->quantity);

        // Completing refund must not restock again (inventory already restored at cancel).
        app(RefundEngine::class)->complete(
            $cancelled->refundTransactions->first(),
            $admin,
        );

        $this->assertSame(8, (int) $fixture['inventory']->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::query()
            ->where('inventory_id', $fixture['inventory']->id)
            ->where('type', 'restock')
            ->count());
    }

    public function test_restock_idempotency_key_replays_safely(): void
    {
        $fixture = $this->makePaidVariantOrder(onHand: 2, qty: 2);

        $this->restock->restockCancelledOrder($fixture['order']);
        $this->restock->restockCancelledOrder($fixture['order']);

        $this->assertSame(4, (int) $fixture['inventory']->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()
            ->where('variant_inventory_id', $fixture['inventory']->id)
            ->where('movement_type', InventoryMovementType::Return->value)
            ->count());
    }

    public function test_ledger_created_for_simple_and_variant(): void
    {
        $simple = $this->makePaidSimpleOrder(onHand: 1, qty: 1);
        $variant = $this->makePaidVariantOrder(onHand: 1, qty: 1);

        $this->restock->restockCancelledOrder($simple['order']);
        $this->restock->restockCancelledOrder($variant['order']);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $simple['inventory']->id,
            'type' => 'restock',
            'quantity' => 1,
            'reason' => 'inventory-cancel-restock:'.$simple['order']->items->first()->id,
        ]);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $variant['inventory']->id,
            'movement_type' => InventoryMovementType::Return->value,
            'quantity_change' => 1,
            'reference_type' => OrderItem::class,
            'reference_id' => $variant['order']->items->first()->id,
        ]);
    }

    public function test_rollback_integrity_on_failure(): void
    {
        $fixture = $this->makePaidSimpleOrder(onHand: 3, qty: 1);
        $order = $fixture['order'];

        // Force failure after restock begins by deleting inventory mid-transaction via nested callback.
        try {
            DB::transaction(function () use ($order, $fixture): void {
                $this->restock->restockCancelledOrder($order);
                // Simulate downstream failure after inventory write.
                throw new \RuntimeException('forced rollback');
            });
            $this->fail('Expected exception');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(3, (int) $fixture['inventory']->fresh()->quantity);
        $this->assertSame(0, InventoryMovement::query()
            ->where('inventory_id', $fixture['inventory']->id)
            ->where('type', 'restock')
            ->count());
    }

    public function test_unpaid_cancel_releases_reservation_holds(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Hold Release Product',
            'price' => 5000,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);
        $inventory = Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 10, 'reserved_quantity' => 3, 'low_stock_threshold' => 1],
        );

        $session = \App\Models\CheckoutSession::factory()->validated()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(30),
        ]);
        $cart = \App\Models\Cart::factory()->create(['user_id' => $user->id, 'currency' => 'TZS']);
        $session->forceFill(['cart_id' => $cart->id])->save();
        \App\Models\CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 3,
            'unit_price' => 5000,
            'currency' => 'TZS',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'checkout_session_id' => $session->id,
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
            'subtotal' => 15000,
            'total' => 15000,
            'currency' => 'TZS',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'sku' => 'HOLD-REL',
            'quantity' => 3,
            'unit_price' => 5000,
            'line_total' => 15000,
            'currency' => 'TZS',
        ]);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        $this->cancel->handle($order->fresh(['items', 'checkoutSession']) ?? $order, 'Abandoned');

        $this->assertSame(10, (int) $inventory->fresh()->quantity);
        $this->assertSame(0, (int) $inventory->fresh()->reserved_quantity);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    /**
     * @return array{order: Order, inventory: Inventory}
     */
    private function makePaidSimpleOrder(int $onHand, int $qty): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Simple Cancel Product',
            'price' => 10000,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);
        $inventory = Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => $onHand, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'subtotal' => 10000 * $qty,
            'total' => 10000 * $qty,
            'currency' => 'TZS',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'sku' => 'SIMPLE-CANCEL',
            'quantity' => $qty,
            'unit_price' => 10000,
            'line_total' => 10000 * $qty,
            'currency' => 'TZS',
        ]);

        return [
            'order' => $order->fresh(['items.product']) ?? $order,
            'inventory' => $inventory,
        ];
    }

    /**
     * @return array{order: Order, inventory: VariantInventory}
     */
    private function makePaidVariantOrder(int $onHand, int $qty): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Variant Cancel Product',
            'price' => 0,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'name' => 'Size L',
            'sku' => 'VAR-CANCEL',
        ]);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'subtotal' => 20000 * $qty,
            'total' => 20000 * $qty,
            'currency' => 'TZS',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => $qty,
            'unit_price' => 20000,
            'line_total' => 20000 * $qty,
            'currency' => 'TZS',
        ]);

        return [
            'order' => $order->fresh(['items.product', 'items.variant']) ?? $order,
            'inventory' => $inventory,
        ];
    }
}
