<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Inventory;
use App\Models\InventoryStockMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\InventoryCommitmentContext;
use App\Services\Inventory\InventoryCommitmentService;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Actions\AdminOrders\PayOrderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2A-3B-3 — InventoryCommitmentService (ADR 055).
 */
class InventoryCommitmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryCommitmentService $commitment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commitment = app(InventoryCommitmentService::class);
    }

    public function test_commits_simple_product_inventory_via_gate(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->makeSimpleOrder(onHand: 10, qty: 3);

        $result = $this->commitment->commitForOrder(new InventoryCommitmentContext(
            order: $order,
            source: 'test',
        ));

        $this->assertTrue($result->committed);
        $this->assertSame(1, $result->itemsCommitted);
        $this->assertSame(7, (int) $inventory->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'quantity' => -3,
            'type' => 'sale',
        ]);
    }

    public function test_commits_variant_product_inventory_via_gate(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->makeVariantOrder(onHand: 12, qty: 4);

        $result = $this->commitment->commitForOrder(new InventoryCommitmentContext(
            order: $order,
            source: 'test',
        ));

        $this->assertTrue($result->committed);
        $this->assertSame(8, (int) $inventory->fresh()->on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'variant_inventory_id' => $inventory->id,
            'movement_type' => InventoryMovementType::Sale->value,
            'quantity_change' => -4,
        ]);
    }

    public function test_duplicate_commit_does_not_double_decrement(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->makeVariantOrder(onHand: 5, qty: 2);

        $this->commitment->commitForOrder(new InventoryCommitmentContext(order: $order, source: 'test'));
        $second = $this->commitment->commitForOrder(new InventoryCommitmentContext(order: $order, source: 'test'));

        $this->assertSame(1, $second->itemsSkippedIdempotent);
        $this->assertSame(3, (int) $inventory->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()->where('variant_inventory_id', $inventory->id)->count());
    }

    public function test_payment_completion_commits_and_is_idempotent_on_retry(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->makeVariantOrder(onHand: 9, qty: 2);
        $order->forceFill([
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
        ])->save();

        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $order->total,
            'currency' => 'TZS',
        ]);

        $completion = app(PaymentTransactionCompletionService::class);
        $payload = new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Successful,
            providerReference: 'ref-1',
            externalTransactionId: 'ext-1',
        );

        $completion->applyResult($transaction, $payload);
        $this->assertSame(7, (int) $inventory->fresh()->on_hand);

        $completion->applyResult($transaction->fresh() ?? $transaction, $payload);
        $this->assertSame(7, (int) $inventory->fresh()->on_hand);
        $this->assertSame(1, InventoryStockMovement::query()->where('variant_inventory_id', $inventory->id)->count());
    }

    public function test_admin_pay_order_commits_simple_inventory(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->makeSimpleOrder(onHand: 6, qty: 2);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        app(PayOrderAction::class)->handle($order);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(4, (int) $inventory->fresh()->quantity);
    }

    public function test_admin_pay_order_commits_variant_inventory(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->makeVariantOrder(onHand: 8, qty: 3);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        app(PayOrderAction::class)->handle($order);

        $this->assertSame(5, (int) $inventory->fresh()->on_hand);
    }

    /**
     * @return array{order: Order, inventory: Inventory}
     */
    private function makeSimpleOrder(int $onHand, int $qty): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Simple Commit Product',
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
            'status' => OrderStatus::Pending,
            'subtotal' => 10000 * $qty,
            'total' => 10000 * $qty,
            'currency' => 'TZS',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'sku' => 'SIMPLE-COMMIT',
            'quantity' => $qty,
            'unit_price' => 10000,
            'line_total' => 10000 * $qty,
            'currency' => 'TZS',
        ]);

        return ['order' => $order->fresh(['items']) ?? $order, 'inventory' => $inventory];
    }

    /**
     * @return array{order: Order, inventory: VariantInventory}
     */
    private function makeVariantOrder(int $onHand, int $qty): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Variant Commit Product',
            'price' => 0,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'name' => 'Size M',
            'sku' => 'VAR-COMMIT',
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
            'status' => OrderStatus::Pending,
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

        return ['order' => $order->fresh(['items.variant', 'items.product']) ?? $order, 'inventory' => $inventory];
    }
}
