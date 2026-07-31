<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\CustomerOrderProgressKey;
use App\Enums\ChinaQcStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\SupplierPoResponse;
use App\Models\PurchaseOrder;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Enums\WarehouseJobStatus;
use App\Enums\WarehouseReleaseStatus;
use App\Models\Admin;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\FulfillmentOperationalReadModelBuilder;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Tracking\TrackingEngine;
use App\Services\Warehouse\WarehouseEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADMIN-04D-3 — verifies admin operational actions propagate through engines,
 * customer progress projection, and customer notifications.
 */
class FulfillmentActionPropagationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return array{0: Order, 1: Fulfillment, 2: User}
     */
    private function makeChinaFulfillmentWithSupplier(): array
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $product = Product::factory()->create(['fulfillment_source' => 'imported_from_china']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'supplier_sku' => 'CN-SKU-1',
            'purchase_cost' => 10000,
            'currency' => 'TZS',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);

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
        $this->assertSame(FulfillmentStrategy::China, $fulfillment->strategy);

        return [$order->fresh(['fulfillment.warehouseJob']), $fulfillment->fresh(['warehouseJob']), $user];
    }

    /**
     * @return array{0: Order, 1: Fulfillment}
     */
    private function makeLocalFulfillment(DeliveryType $deliveryType = DeliveryType::SelfPickup): array
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

        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => $deliveryType,
            'shipping_method' => $deliveryType === DeliveryType::CompanyShipping
                ? DeliveryShippingMethod::Air
                : null,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));
        $this->assertSame(FulfillmentStrategy::Local, $fulfillment->strategy);

        return [$order->fresh(['fulfillment.warehouseJob', 'deliveryOption']), $fulfillment->fresh(['warehouseJob'])];
    }

    public function test_china_purchase_bootstrap_advances_customer_progress_without_fulfilment_status_change(): void
    {
        [$order, $fulfillment] = $this->makeChinaFulfillmentWithSupplier();
        $resolver = app(CustomerOrderProgressResolver::class);

        $before = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments']));

        app(ChinaWorkflowEngine::class)->bootstrapFromFulfillment($fulfillment);

        $order->refresh();
        $fulfillment->refresh();
        $after = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments']));

        $this->assertSame(FulfillmentStatus::Pending, $fulfillment->status);
        $this->assertSame(CustomerOrderProgressKey::Preparing, CustomerOrderProgressKey::from($after['current_key']));
        $this->assertSame(
            CustomerOrderProgressKey::from($before['current_key']),
            CustomerOrderProgressKey::from($after['current_key']),
        );
        $this->assertDatabaseHas('china_workflow_histories', [
            'order_id' => $order->id,
            'action' => 'procurement_started',
        ]);
        $this->assertSame(0, Notification::query()->where('user_id', $order->user_id)->count());
    }

    public function test_tz_local_warehouse_ready_progress_has_no_china_operational_section(): void
    {
        [$order, $fulfillment] = $this->makeLocalFulfillment();
        $job = $fulfillment->warehouseJob;
        $this->assertNotNull($job);

        $resolver = app(CustomerOrderProgressResolver::class);
        $warehouse = app(WarehouseEngine::class);

        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
        ] as $status) {
            $job = $warehouse->updateStatus($job, ['status' => $status->value]);
            $progress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments']));
            $this->assertSame(CustomerOrderProgressKey::Preparing, CustomerOrderProgressKey::from($progress['current_key']));
        }

        $warehouse->updateStatus($job, ['status' => WarehouseJobStatus::ReadyToShip->value]);
        $fulfillment->refresh();
        $progress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments']));

        $this->assertSame(FulfillmentStatus::ReadyForShipping, $fulfillment->fresh()->status);
        $this->assertSame(CustomerOrderProgressKey::ReadyToShip, CustomerOrderProgressKey::from($progress['current_key']));
        $this->assertSame('Order ready', $progress['current_label']);
        $this->assertSame([
            'ORDER_CONFIRMED',
            'PREPARING',
            'READY_TO_SHIP',
            'DELIVERED',
        ], array_column($progress['steps'], 'key'));

        $payload = app(FulfillmentOperationalReadModelBuilder::class)->build($fulfillment->fresh([
            'order.items',
            'order.deliveryOption',
            'warehouseJob',
        ]));
        $this->assertNull($payload['china']);
    }

    public function test_shipment_dispatch_and_delivery_update_progress_and_notify_customer(): void
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
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'shipping_method' => DeliveryShippingMethod::Air,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));
        app(FulfillmentEngine::class)->updateStatus($fulfillment, ['status' => FulfillmentStatus::Processing->value]);
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), ['status' => FulfillmentStatus::ReadyForShipping->value]);
        WarehouseJob::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->update(['status' => WarehouseJobStatus::ReadyToShip->value]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::Pending,
        ]);

        $resolver = app(CustomerOrderProgressResolver::class);
        $tracking = app(TrackingEngine::class);

        $tracking->recordEvent($shipment, [
            'event_type' => TrackingEventType::DepartedOrigin->value,
            'location' => 'Dar warehouse',
        ]);

        $shippedProgress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments']));
        $this->assertSame(CustomerOrderProgressKey::Shipped, CustomerOrderProgressKey::from($shippedProgress['current_key']));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::TrackingUpdated->value,
        ]);

        $tracking->recordEvent($shipment->fresh(), [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $deliveredProgress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments']));
        $this->assertSame(CustomerOrderProgressKey::Delivered, CustomerOrderProgressKey::from($deliveredProgress['current_key']));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::OrderDelivered->value,
        ]);
    }

    private function advanceChinaOrderToExportReady(Order $order, Fulfillment $fulfillment, Admin $admin): void
    {
        Sanctum::actingAs($admin);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        app(ChinaWorkflowEngine::class)->recordSupplierResponse(
            $po,
            SupplierPoResponse::Accepted,
            null,
            $admin,
        );

        $itemId = $po->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();

        app(ChinaWorkflowEngine::class)->recordQc(
            $order->fresh(),
            ChinaQcStatus::Passed,
            null,
            $admin,
        );

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Processing->value,
        ]);

        $job = WarehouseJob::query()->where('fulfillment_id', $fulfillment->id)->firstOrFail();
        $warehouse = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ] as $status) {
            $job = $warehouse->updateStatus($job->fresh(), ['status' => $status->value]);
        }

        app(ChinaWorkflowEngine::class)->markExportReady($order->fresh(), $admin, [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ]);

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
    }

    public function test_customer_agent_handover_advances_progress_without_company_shipment(): void
    {
        [$order, $fulfillment, $user] = $this->makeChinaFulfillmentWithSupplier();
        app(ChinaWorkflowEngine::class)->bootstrapFromFulfillment($fulfillment);

        DeliveryOption::factory()->customerAgent()->create(['order_id' => $order->id]);

        $admin = Admin::factory()->superAdmin()->create();
        $this->advanceChinaOrderToExportReady($order, $fulfillment, $admin);

        $agent = app(CustomerAgentWorkflowEngine::class);
        $agent->bootstrap($order->fresh(['deliveryOption']));
        $agent->authorize($order->fresh(['deliveryOption']), $admin);
        $agent->schedulePickup($order->fresh(['deliveryOption']), $admin);
        $agent->transitionWarehouseRelease(
            $order->fresh(['deliveryOption']),
            $admin,
            WarehouseReleaseStatus::PickedUp,
        );
        $agent->transitionWarehouseRelease(
            $order->fresh(['deliveryOption']),
            $admin,
            WarehouseReleaseStatus::Released,
        );
        $agent->completeHandover($order->fresh(['deliveryOption']), $admin, [
            'reference_number' => 'PU-001',
        ]);

        $resolver = app(CustomerOrderProgressResolver::class);
        $progress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments', 'deliveryOption']));
        $this->assertSame(CustomerOrderProgressKey::DeliveredToAgent, CustomerOrderProgressKey::from($progress['current_key']));

        $this->assertNull(
            Shipment::query()->where('fulfillment_id', $fulfillment->id)->first(),
        );
        $this->assertFalse(
            app(\App\Services\Shipments\ShipmentEligibilityService::class)->evaluate(
                $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment'])
            )['eligible']
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::AgentHandoverCompleted->value,
        ]);
        $this->assertSame(
            FulfillmentStatus::Delivered,
            $fulfillment->fresh()->status,
        );
    }

    public function test_create_shipment_emits_customer_notification_and_ready_to_ship_progress(): void
    {
        [$order, $fulfillment] = $this->makeLocalFulfillment(DeliveryType::CompanyShipping);
        app(FulfillmentEngine::class)->updateStatus($fulfillment, ['status' => FulfillmentStatus::Processing->value]);
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), ['status' => FulfillmentStatus::ReadyForShipping->value]);
        WarehouseJob::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->update(['status' => WarehouseJobStatus::ReadyToShip->value]);

        app(ShipmentEngine::class)->createForFulfillment($fulfillment->fresh(['order.deliveryOption', 'warehouseJob']));

        $progress = app(CustomerOrderProgressResolver::class)->resolve(
            $order->fresh(['fulfillment.warehouseJob', 'shipments'])
        );
        $this->assertSame(CustomerOrderProgressKey::ReadyToShip, CustomerOrderProgressKey::from($progress['current_key']));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::ShipmentCreated->value,
        ]);
    }
}
