<?php

namespace Tests\Feature\Fulfillment;

use App\Actions\AdminOrders\CancelOrderAction;
use App\Actions\AdminOrders\CompleteCancellationRefundAction;
use App\Actions\CustomerOrders\CancelCustomerOrderAction;
use App\Enums\CustomerOrderProgressKey;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\FulfillmentStatusHistory;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\FulfillmentOperationalReadModelBuilder;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Returns\RefundEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADMIN-04D-4 — fulfilment data integrity and edge-case regression coverage.
 */
class FulfillmentDataIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return array{0: Order, 1: \App\Models\Fulfillment}
     */
    private function makePaidOrderWithFulfillment(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'buy_from_tz']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));

        return [$order->fresh(['fulfillment.warehouseJob']), $fulfillment->fresh(['warehouseJob'])];
    }

    public function test_pending_payment_order_has_no_fulfilment_or_warehouse(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertNull($order->fulfillment);
        $this->assertSame(0, WarehouseJob::query()->where('order_id', $order->id)->count());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));
    }

    public function test_admin_cancel_sets_fulfilment_cancelled_with_engine_history(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Customer request');

        $fulfillment->refresh();
        $order->refresh();

        $this->assertSame(OrderStatus::RefundPending, $order->status);
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->status);
        $this->assertNotNull($fulfillment->completed_at);

        $this->assertSame(
            1,
            FulfillmentStatusHistory::query()
                ->where('fulfillment_id', $fulfillment->id)
                ->where('source', \App\Enums\FulfillmentStatusHistorySource::OrderCancel->value)
                ->count(),
        );
    }

    public function test_customer_cancel_cancels_fulfilment_for_paid_orders(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $user = User::findOrFail($order->user_id);

        app(CancelCustomerOrderAction::class)->handle($user, $order, 'Changed mind');

        $order->refresh();
        $fulfillment->refresh();

        $this->assertSame(OrderStatus::RefundPending, $order->status);
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->status);
    }

    public function test_refund_complete_does_not_re_terminalize_already_cancelled_fulfilment(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Cancel before refund');
        $order = $order->fresh(['refundTransactions']);
        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->status);

        $refund = app(RefundEngine::class)->ensureCancellationRefundPending($order, $admin);
        app(CompleteCancellationRefundAction::class)->handle($order->fresh(), [
            'amount' => $order->total,
            'reference' => 'OFFLINE-REF-001',
            'notes' => 'Manual refund confirmed',
            'confirm' => true,
        ]);

        $order->refresh();
        $fulfillment->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(RefundTransactionStatus::Completed, $refund->fresh()->status);
        // Refund completion does not mutate fulfillment — stays as admin-cancelled.
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->status);
    }

    public function test_duplicate_china_purchase_bootstrap_is_idempotent(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $product = Product::factory()->create(['fulfillment_source' => 'imported_from_china']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'supplier_sku' => 'CN-1',
            'purchase_cost' => 10000,
            'currency' => 'TZS',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.variant']));
        $engine = app(ChinaWorkflowEngine::class);

        $engine->bootstrapFromFulfillment($fulfillment);
        $engine->bootstrapFromFulfillment($fulfillment->fresh());

        $this->assertSame(1, PurchaseOrder::query()->where('order_id', $order->id)->count());
    }

    public function test_legacy_fulfilment_without_history_still_serves_operational_read_model(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();

        $payload = app(FulfillmentOperationalReadModelBuilder::class)->build(
            $fulfillment->fresh(['order.items', 'order.deliveryOption', 'warehouseJob', 'shipment', 'statusHistories']),
        );

        $this->assertSame([], $payload['status_history']);
        $this->assertNotNull($payload['customer_progress']['current_key']);
        $this->assertSame($fulfillment->id, $payload['fulfillment']['id']);
        $this->assertNull($payload['china']);
    }

    public function test_cancelled_fulfilment_blocks_invalid_shipped_transition_via_engine(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Cancelled->value,
        ], new \App\Services\Fulfillment\FulfillmentStatusUpdateContext(
            source: \App\Enums\FulfillmentStatusHistorySource::Admin,
        ));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);
    }

    public function test_admin_cancel_cascades_warehouse_job_to_cancelled(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $job = $fulfillment->warehouseJob;
        $this->assertNotNull($job);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        app(CancelOrderAction::class)->handle($order, 'Cancel during warehouse prep');

        $job->refresh();
        $this->assertSame(WarehouseJobStatus::Cancelled, $job->status);
    }

    public function test_refunded_order_customer_progress_shows_terminal_refunded_state(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Refund path');
        $order = $order->fresh(['refundTransactions', 'fulfillment.warehouseJob']);
        app(RefundEngine::class)->ensureCancellationRefundPending($order, $admin);
        app(CompleteCancellationRefundAction::class)->handle($order->fresh(), [
            'amount' => $order->total,
            'reference' => 'OFFLINE-REF-002',
            'confirm' => true,
        ]);

        $progress = app(CustomerOrderProgressResolver::class)->resolve(
            $order->fresh(['fulfillment.warehouseJob', 'shipments', 'payments']),
        );

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(CustomerOrderProgressKey::Refunded->value, $progress['current_key']);
    }

    public function test_cancelled_order_does_not_emit_duplicate_refund_notifications_on_replay(): void
    {
        [$order] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Refund path');
        $order = $order->fresh(['refundTransactions']);
        $refund = app(RefundEngine::class)->ensureCancellationRefundPending($order, $admin);

        app(CompleteCancellationRefundAction::class)->handle($order->fresh(), [
            'amount' => $order->total,
            'reference' => 'OFFLINE-REF-003',
            'confirm' => true,
        ]);

        $before = Notification::query()->where('user_id', $order->user_id)->count();

        app(CompleteCancellationRefundAction::class)->handle($order->fresh(), [
            'amount' => $order->total,
            'reference' => 'OFFLINE-REF-003-REPLAY',
            'confirm' => true,
        ]);

        $this->assertSame($before, Notification::query()->where('user_id', $order->user_id)->count());
        $this->assertSame(RefundTransactionStatus::Completed, $refund->fresh()->status);
    }
}
