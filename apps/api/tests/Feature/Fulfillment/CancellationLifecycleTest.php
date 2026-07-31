<?php

namespace Tests\Feature\Fulfillment;

use App\Actions\AdminOrders\CancelOrderAction;
use App\Actions\AdminOrders\CompleteCancellationRefundAction;
use App\Actions\CustomerOrders\CancelCustomerOrderAction;
use App\Enums\CustomerOrderProgressKey;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\FulfillmentStatusHistory;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Returns\RefundEngine;
use App\Services\Warehouse\WarehouseEngine;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADMIN-04D-5 — cancellation lifecycle hardening regression coverage.
 */
class CancellationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
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

    public function test_admin_cancel_creates_fulfilment_history_via_engine(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Customer request');

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->status);

        $history = FulfillmentStatusHistory::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->latest()
            ->first();

        $this->assertNotNull($history);
        $this->assertSame(FulfillmentStatus::Pending->value, $history->from_status);
        $this->assertSame(FulfillmentStatus::Cancelled->value, $history->to_status);
        $this->assertSame(FulfillmentStatusHistorySource::OrderCancel, $history->source);
    }

    public function test_customer_cancel_cancels_fulfilment_with_engine_history(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $user = User::findOrFail($order->user_id);

        app(CancelCustomerOrderAction::class)->handle($user, $order, 'Changed mind');

        $fulfillment->refresh();
        $order->refresh();

        $this->assertSame(OrderStatus::RefundPending, $order->status);
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->status);
        $this->assertSame(
            1,
            FulfillmentStatusHistory::query()
                ->where('fulfillment_id', $fulfillment->id)
                ->where('source', FulfillmentStatusHistorySource::OrderCancel->value)
                ->count(),
        );
    }

    public function test_cancelled_fulfilment_cannot_transition_to_shipped(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Cancel before ship');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);
    }

    public function test_admin_cancel_cascades_pending_warehouse_job_to_cancelled(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $job = $fulfillment->warehouseJob;
        $this->assertNotNull($job);
        $this->assertSame(WarehouseJobStatus::Pending, $job->status);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        app(CancelOrderAction::class)->handle($order, 'Cancel during warehouse prep');

        $job->refresh();
        $this->assertSame(WarehouseJobStatus::Cancelled, $job->status);
    }

    public function test_admin_cancel_cascades_picking_warehouse_job_to_cancelled(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $job = $fulfillment->warehouseJob;
        $this->assertNotNull($job);

        app(WarehouseEngine::class)->updateStatus($job, [
            'status' => WarehouseJobStatus::Picking->value,
        ]);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        app(CancelOrderAction::class)->handle($order, 'Cancel during picking');

        $job->refresh();
        $this->assertSame(WarehouseJobStatus::Cancelled, $job->status);
    }

    public function test_customer_progress_shows_refund_pending_after_paid_cancel(): void
    {
        [$order] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Refund path');

        $progress = app(CustomerOrderProgressResolver::class)->resolve(
            $order->fresh(['fulfillment.warehouseJob', 'shipments', 'payments']),
        );

        $this->assertSame(CustomerOrderProgressKey::RefundPending->value, $progress['current_key']);
        $this->assertSame('Refund processing', $progress['current_label']);
    }

    public function test_customer_progress_shows_refunded_after_cancellation_refund_complete(): void
    {
        [$order] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Refund path');
        $order = $order->fresh(['refundTransactions']);
        app(RefundEngine::class)->ensureCancellationRefundPending($order, $admin);
        app(CompleteCancellationRefundAction::class)->handle($order->fresh(), [
            'amount' => $order->total,
            'reference' => 'OFFLINE-REF-CANCEL',
            'confirm' => true,
        ]);

        $progress = app(CustomerOrderProgressResolver::class)->resolve(
            $order->fresh(['fulfillment.warehouseJob', 'shipments', 'payments']),
        );

        $this->assertSame(CustomerOrderProgressKey::Refunded->value, $progress['current_key']);
        $this->assertSame('Refund completed', $progress['current_label']);
    }

    public function test_cancellation_emits_order_cancelled_and_refund_started_notifications(): void
    {
        [$order] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'Notify test');

        $order = $order->fresh(['refundTransactions']);

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $order->user_id)
                ->where('event_type', NotificationEventType::OrderCancelled->value)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $order->user_id)
                ->where('event_type', NotificationEventType::RefundStarted->value)
                ->exists(),
        );
    }

    public function test_already_cancelled_fulfilment_cancel_is_idempotent(): void
    {
        [$order, $fulfillment] = $this->makePaidOrderWithFulfillment();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order, 'First cancel');
        $historyCount = FulfillmentStatusHistory::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->count();

        app(CancelOrderAction::class)->handle($order->fresh(), 'Replay cancel');

        $this->assertSame(
            $historyCount,
            FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count(),
        );
    }
}
