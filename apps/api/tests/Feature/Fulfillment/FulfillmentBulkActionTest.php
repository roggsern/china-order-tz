<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ActivityEventType;
use App\Enums\DeliveryType;
use App\Enums\DeliveryShippingMethod;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\WarehouseJobStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Shipment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\DeliveryOption;
use App\Enums\DeliveryOptionStatus;
use App\Enums\LastMileReceivingMethod;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Services\Fulfillment\CompanyShippingHandoverService;
use App\Services\Orders\CompanyShippingReceivingChoiceService;
use App\Services\Tracking\TrackingEngine;
use App\Models\Fulfillment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Enums\SupplierPoResponse;
use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\CustomerOrderProgressKey;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Procurement\ReceivingEngine;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Warehouse\WarehouseEngine;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_bulk_mark_local_order_ready_processes_multiple_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $first = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $second = $this->createLocalPendingFulfillment(DeliveryType::NegotiatedDelivery);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_READY',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0);

        $first->refresh();
        $second->refresh();
        $this->assertSame(FulfillmentStatus::ReadyForShipping, $first->status);
        $this->assertSame(FulfillmentStatus::ReadyForShipping, $second->status);
        $this->assertSame(WarehouseJobStatus::ReadyToShip, $first->warehouseJob?->status);
        $this->assertSame(WarehouseJobStatus::ReadyToShip, $second->warehouseJob?->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $first->order?->user_id,
            'type' => NotificationEventType::WarehouseReadyForPickup->value,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $second->order?->user_id,
            'type' => NotificationEventType::WarehouseReadyForDeliveryArrangement->value,
        ]);
    }

    public function test_bulk_action_skips_ineligible_orders_without_failing_batch(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaPendingFulfillment();
        $agent = $this->createLocalPendingFulfillment(DeliveryType::CustomerAgent);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_READY',
            'fulfillment_ids' => [$local->id, $china->id, $agent->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 2)
            ->assertJsonPath('data.failed', 0);

        $local->refresh();
        $this->assertSame(FulfillmentStatus::ReadyForShipping, $local->status);
    }

    public function test_bulk_action_requires_warehouse_permissions(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_READY',
            'fulfillment_ids' => [$local->id],
        ])->assertForbidden();
    }

    public function test_bulk_action_records_audit_log_with_batch_id(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_READY',
            'fulfillment_ids' => [$local->id],
        ]);

        $batchId = $response->json('data.batch_id');
        $this->assertNotEmpty($batchId);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::FulfillmentBulkActionCompleted->value,
            'actor_id' => $admin->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentBulkActionCompleted)
            ->first();
        $this->assertSame($batchId, $log?->metadata['batch_id'] ?? null);
    }

    public function test_bulk_mark_local_order_completed_processes_ready_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $pickup = $this->createReadyLocalFulfillment(DeliveryType::SelfPickup);
        $delivery = $this->createReadyLocalFulfillment(DeliveryType::NegotiatedDelivery);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_COMPLETED',
            'fulfillment_ids' => [$pickup->id, $delivery->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $pickup->refresh();
        $delivery->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $pickup->status);
        $this->assertSame(FulfillmentStatus::Delivered, $delivery->status);
        $this->assertNotNull($pickup->completed_at);
        $this->assertNotNull($delivery->completed_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $pickup->order?->user_id,
            'event_type' => NotificationEventType::LocalOrderCompletedPickup->value,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $delivery->order?->user_id,
            'event_type' => NotificationEventType::LocalOrderCompletedDeliveryArrangement->value,
        ]);
    }

    public function test_bulk_complete_skips_ineligible_orders_without_failing_batch(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $ready = $this->createReadyLocalFulfillment(DeliveryType::SelfPickup);
        $pending = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaPendingFulfillment();
        $agent = $this->createLocalPendingFulfillment(DeliveryType::CustomerAgent);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_COMPLETED',
            'fulfillment_ids' => [$ready->id, $pending->id, $china->id, $agent->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 3)
            ->assertJsonPath('data.failed', 0);

        $ready->refresh();
        $pending->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $ready->status);
        $this->assertNotSame(FulfillmentStatus::Delivered, $pending->status);
    }

    public function test_bulk_complete_requires_orders_fulfill_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
            AdminPermissions::WAREHOUSE_JOBS_COMPLETE,
        ])->create();
        Sanctum::actingAs($admin);

        $ready = $this->createReadyLocalFulfillment(DeliveryType::SelfPickup);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_LOCAL_ORDER_COMPLETED',
            'fulfillment_ids' => [$ready->id],
        ])->assertForbidden();
    }

    public function test_bulk_create_supplier_purchase_processes_multiple_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaFulfillmentWithoutPurchase();
        $second = $this->createChinaFulfillmentWithoutPurchase();

        Product::query()->whereKey([
            $first->order?->items()->value('product_id'),
            $second->order?->items()->value('product_id'),
        ])->update(['supplier_id' => $supplier->id]);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SUPPLIER_PURCHASE',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $this->assertSame(1, PurchaseOrder::query()->where('fulfillment_id', $first->id)->count());
        $this->assertSame(1, PurchaseOrder::query()->where('fulfillment_id', $second->id)->count());
    }

    public function test_bulk_create_supplier_purchase_skips_existing_purchases(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $existing = $this->createChinaFulfillmentWithPurchase($supplier);
        $pending = $this->createChinaFulfillmentWithoutPurchase();
        Product::query()->whereKey($pending->order?->items()->value('product_id'))
            ->update(['supplier_id' => $supplier->id]);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SUPPLIER_PURCHASE',
            'fulfillment_ids' => [$existing->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_create_supplier_purchase_fails_only_unmapped_supplier_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $mapped = $this->createChinaFulfillmentWithoutPurchase();
        $unmapped = $this->createChinaFulfillmentWithoutPurchase();
        Product::query()->whereKey($mapped->order?->items()->value('product_id'))
            ->update(['supplier_id' => $supplier->id]);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SUPPLIER_PURCHASE',
            'fulfillment_ids' => [$mapped->id, $unmapped->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.skipped', 0);
    }

    public function test_bulk_create_supplier_purchase_requires_procurement_create_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $fulfillment = $this->createChinaFulfillmentWithoutPurchase();

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SUPPLIER_PURCHASE',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_create_supplier_purchase_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaFulfillmentWithoutPurchase();
        Product::query()->whereKey($china->order?->items()->value('product_id'))
            ->update(['supplier_id' => $supplier->id]);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SUPPLIER_PURCHASE',
            'fulfillment_ids' => [$local->id, $china->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_receive_goods_processes_multiple_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaFulfillmentReadyToReceive($supplier);
        $second = $this->createChinaFulfillmentReadyToReceive($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'RECEIVE_GOODS',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $firstPo = PurchaseOrder::query()->where('fulfillment_id', $first->id)->firstOrFail();
        $secondPo = PurchaseOrder::query()->where('fulfillment_id', $second->id)->firstOrFail();
        $this->assertSame(PurchaseOrderStatus::Completed, $firstPo->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::Completed, $secondPo->fresh()->status);
    }

    public function test_bulk_receive_goods_skips_already_received_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $received = $this->createChinaFulfillmentReadyToReceive($supplier);
        $pending = $this->createChinaFulfillmentReadyToReceive($supplier);

        $receivedPo = PurchaseOrder::query()->where('fulfillment_id', $received->id)->firstOrFail();
        $items = $receivedPo->items->map(fn ($item) => [
            'purchase_order_item_id' => $item->id,
            'quantity' => $item->quantityOutstanding(),
        ])->all();
        app(ReceivingEngine::class)->receive($receivedPo, ['items' => $items], $admin);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'RECEIVE_GOODS',
            'fulfillment_ids' => [$received->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_receive_goods_requires_purchase_orders_receive_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyToReceive($supplier);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'RECEIVE_GOODS',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_receive_goods_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaFulfillmentReadyToReceive($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'RECEIVE_GOODS',
            'fulfillment_ids' => [$local->id, $china->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_qc_passed_processes_multiple_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaFulfillmentReadyForQc($supplier);
        $second = $this->createChinaFulfillmentReadyForQc($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_QC_PASSED',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $first->refresh();
        $second->refresh();
        $this->assertSame(ChinaQcStatus::Passed, $first->chinaWorkflowRecord?->qc_status);
        $this->assertSame(ChinaQcStatus::Passed, $second->chinaWorkflowRecord?->qc_status);
        $this->assertSame(ChinaWorkflowStage::QcPassed, $first->chinaWorkflowRecord?->stage);
        $this->assertSame(ChinaWorkflowStage::QcPassed, $second->chinaWorkflowRecord?->stage);
    }

    public function test_bulk_mark_qc_passed_skips_already_passed_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $passed = $this->createChinaFulfillmentReadyForQc($supplier);
        $pending = $this->createChinaFulfillmentReadyForQc($supplier);

        app(ChinaWorkflowEngine::class)->recordQc(
            $passed->order,
            ChinaQcStatus::Passed,
            null,
            $admin,
        );

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_QC_PASSED',
            'fulfillment_ids' => [$passed->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_qc_passed_skips_orders_without_received_goods(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $notReceived = $this->createChinaFulfillmentReadyToReceive($supplier);
        $ready = $this->createChinaFulfillmentReadyForQc($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_QC_PASSED',
            'fulfillment_ids' => [$notReceived->id, $ready->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_qc_passed_requires_procurement_update_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForQc($supplier);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_QC_PASSED',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_mark_qc_passed_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaFulfillmentReadyForQc($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_QC_PASSED',
            'fulfillment_ids' => [$local->id, $china->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_china_packing_complete_processes_multiple_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaFulfillmentReadyForPacking($supplier);
        $second = $this->createChinaFulfillmentReadyForPacking($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CHINA_PACKING_COMPLETE',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $first->refresh();
        $second->refresh();
        $this->assertSame(WarehouseJobStatus::Packed, $first->warehouseJob?->status);
        $this->assertSame(WarehouseJobStatus::Packed, $second->warehouseJob?->status);
    }

    public function test_bulk_mark_china_packing_complete_advances_warehouse_transitions_to_packed(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForPacking($supplier);
        $job = $fulfillment->warehouseJob;
        $this->assertInstanceOf(WarehouseJob::class, $job);

        app(WarehouseEngine::class)->updateStatus($job, ['status' => WarehouseJobStatus::Picking->value]);
        $fulfillment->refresh();

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CHINA_PACKING_COMPLETE',
            'fulfillment_ids' => [$fulfillment->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $fulfillment->refresh();
        $this->assertSame(WarehouseJobStatus::Packed, $fulfillment->warehouseJob?->status);
        $this->assertNotSame(WarehouseJobStatus::ReadyToShip, $fulfillment->warehouseJob?->status);
    }

    public function test_bulk_mark_china_packing_complete_skips_qc_pending_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $qcPending = $this->createChinaFulfillmentReadyForQc($supplier);
        $ready = $this->createChinaFulfillmentReadyForPacking($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CHINA_PACKING_COMPLETE',
            'fulfillment_ids' => [$qcPending->id, $ready->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_china_packing_complete_requires_warehouse_permissions(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForPacking($supplier);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CHINA_PACKING_COMPLETE',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_mark_china_packing_complete_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaFulfillmentReadyForPacking($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CHINA_PACKING_COMPLETE',
            'fulfillment_ids' => [$local->id, $china->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_china_packing_complete_supports_customer_agent_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForPacking($supplier);
        DeliveryOption::query()
            ->where('order_id', $fulfillment->order_id)
            ->update(['delivery_type' => DeliveryType::CustomerAgent]);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CHINA_PACKING_COMPLETE',
            'fulfillment_ids' => [$fulfillment->fresh()->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.failed', 0);

        $fulfillment->refresh();
        $this->assertSame(WarehouseJobStatus::Packed, $fulfillment->warehouseJob?->status);
    }

    public function test_bulk_mark_export_ready_processes_multiple_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaFulfillmentReadyForExport($supplier);
        $second = $this->createChinaFulfillmentReadyForExport($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $first->refresh();
        $second->refresh();
        $this->assertTrue($first->chinaWorkflowRecord?->isAuthoritativelyExportReady());
        $this->assertTrue($second->chinaWorkflowRecord?->isAuthoritativelyExportReady());
        $this->assertSame(ChinaWorkflowStage::CompanyShippingReady, $first->chinaWorkflowRecord?->stage);
        $this->assertSame(ChinaWorkflowStage::CompanyShippingReady, $second->chinaWorkflowRecord?->stage);
    }

    public function test_bulk_mark_export_ready_skips_customer_agent_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $agent = $this->createChinaFulfillmentReadyForExport($supplier);
        DeliveryOption::query()
            ->where('order_id', $agent->order_id)
            ->update(['delivery_type' => DeliveryType::CustomerAgent]);
        $company = $this->createChinaFulfillmentReadyForExport($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$agent->fresh()->id, $company->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('skipped', $results[(string) $agent->fresh()->id]['status']);
        $this->assertSame('WRONG_DELIVERY_TYPE', $results[(string) $agent->fresh()->id]['reason_code']);
        $this->assertSame('succeeded', $results[(string) $company->id]['status']);
    }

    public function test_bulk_mark_export_ready_skips_packed_warehouse_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $packedOnly = $this->createChinaFulfillmentPackedOnly($supplier);
        $ready = $this->createChinaFulfillmentReadyForExport($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$packedOnly->id, $ready->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('skipped', $results[(string) $packedOnly->id]['status']);
        $this->assertSame('WAREHOUSE_NOT_READY', $results[(string) $packedOnly->id]['reason_code']);
        $this->assertStringContainsString('ready to ship', strtolower((string) $results[(string) $packedOnly->id]['reason']));
        $this->assertFalse($packedOnly->fresh()->chinaWorkflowRecord?->isAuthoritativelyExportReady() ?? true);
    }

    public function test_bulk_mark_export_ready_eligibility_matches_single_order_resolver(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentPackedOnly($supplier);
        $order = $fulfillment->order;
        $record = $fulfillment->chinaWorkflowRecord;
        $this->assertNotNull($order);
        $this->assertNotNull($record);

        $engine = app(ChinaWorkflowEngine::class);
        $checklist = [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ];

        $this->assertSame(WarehouseJobStatus::Packed, $fulfillment->warehouseJob?->status);
        // Engine blockers accept packed warehouse; admin single-order and bulk both require ready_to_ship.
        $this->assertEmpty($engine->evaluateExportReadinessBlockers($record, $order, $checklist));

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$fulfillment->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 0)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.results.0.status', 'skipped')
            ->assertJsonPath('data.results.0.reason_code', 'WAREHOUSE_NOT_READY');
    }

    public function test_bulk_action_results_include_status_reason_code_and_reason(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $ready = $this->createChinaFulfillmentReadyForExport($supplier);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$ready->id, $local->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('succeeded', $results[(string) $ready->id]['status']);
        $this->assertTrue($results[(string) $ready->id]['success']);
        $this->assertArrayNotHasKey('reason_code', $results[(string) $ready->id]);

        $this->assertSame('skipped', $results[(string) $local->id]['status']);
        $this->assertFalse($results[(string) $local->id]['success']);
        $this->assertSame('WRONG_STRATEGY', $results[(string) $local->id]['reason_code']);
        $this->assertNotEmpty($results[(string) $local->id]['reason']);
    }

    public function test_bulk_mark_export_ready_partial_success_still_processes_eligible_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $existing = $this->createChinaFulfillmentReadyForExport($supplier);
        $pending = $this->createChinaFulfillmentReadyForExport($supplier);

        app(ChinaWorkflowEngine::class)->markExportReady(
            $existing->order,
            $admin,
            [
                'commercial_invoice' => true,
                'packing_list' => true,
                'customs_docs' => true,
                'weight_confirmed' => true,
                'dimensions_confirmed' => true,
            ],
        );

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$existing->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('skipped', $results[(string) $existing->id]['status']);
        $this->assertSame('ALREADY_EXPORT_READY', $results[(string) $existing->id]['reason_code']);
        $this->assertSame('succeeded', $results[(string) $pending->id]['status']);
        $this->assertTrue($pending->fresh()->chinaWorkflowRecord?->isAuthoritativelyExportReady());
    }

    public function test_bulk_mark_export_ready_requires_warehouse_jobs_complete_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForExport($supplier);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_mark_export_ready_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $china = $this->createChinaFulfillmentReadyForExport($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_EXPORT_READY',
            'fulfillment_ids' => [$local->id, $china->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_agent_delivered_processes_multiple_china_customer_agent_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);
        $second = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_AGENT_DELIVERED',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $first->refresh();
        $second->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $first->status);
        $this->assertSame(FulfillmentStatus::Delivered, $second->status);
        $this->assertNotNull($first->completed_at);
        $this->assertNotNull($second->completed_at);
    }

    public function test_bulk_mark_agent_delivered_skips_company_shipping_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $company = $this->createChinaFulfillmentReadyForExport($supplier);
        $agent = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_AGENT_DELIVERED',
            'fulfillment_ids' => [$company->id, $agent->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_agent_delivered_skips_already_delivered_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $existing = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);
        $pending = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);

        app(CustomerAgentWorkflowEngine::class)->completeHandover($existing->order, $admin);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_AGENT_DELIVERED',
            'fulfillment_ids' => [$existing->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_mark_agent_delivered_requires_orders_ship_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_AGENT_DELIVERED',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_mark_agent_delivered_updates_customer_progress_and_notifications(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);
        $order = $fulfillment->order;

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_AGENT_DELIVERED',
            'fulfillment_ids' => [$fulfillment->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.failed', 0);

        $progress = app(CustomerOrderProgressResolver::class)->resolve(
            $order->fresh(['fulfillment.warehouseJob', 'shipments', 'deliveryOption']),
        );
        $this->assertSame(
            CustomerOrderProgressKey::DeliveredToAgent,
            CustomerOrderProgressKey::from($progress['current_key']),
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::AgentHandoverCompleted->value,
        ]);
    }

    public function test_bulk_mark_agent_delivered_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $agent = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_AGENT_DELIVERED',
            'fulfillment_ids' => [$local->id, $agent->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_create_shipment_processes_multiple_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $first = $this->createChinaFulfillmentReadyForShipment($supplier);
        $second = $this->createChinaFulfillmentReadyForShipment($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $this->assertSame(1, Shipment::query()->where('fulfillment_id', $first->id)->count());
        $this->assertSame(1, Shipment::query()->where('fulfillment_id', $second->id)->count());
    }

    public function test_bulk_create_shipment_skips_export_not_ready_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $notReady = $this->createChinaFulfillmentReadyForExport($supplier);
        app(FulfillmentEngine::class)->updateStatus($notReady->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
        DeliveryOption::query()
            ->where('order_id', $notReady->order_id)
            ->update(['shipping_method' => DeliveryShippingMethod::Air]);
        $ready = $this->createChinaFulfillmentReadyForShipment($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$notReady->id, $ready->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_create_shipment_skips_existing_shipments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $existing = $this->createChinaFulfillmentReadyForShipment($supplier);
        $pending = $this->createChinaFulfillmentReadyForShipment($supplier);

        app(ShipmentEngine::class)->createForFulfillment($existing->fresh(['order.deliveryOption', 'warehouseJob', 'shipment']));

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$existing->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_create_shipment_skips_customer_agent_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $agent = $this->createChinaFulfillmentReadyForShipment($supplier);
        DeliveryOption::query()
            ->where('order_id', $agent->order_id)
            ->update(['delivery_type' => DeliveryType::CustomerAgent]);
        $company = $this->createChinaFulfillmentReadyForShipment($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$agent->fresh()->id, $company->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_create_shipment_requires_orders_ship_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForShipment($supplier);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }

    public function test_bulk_create_shipment_triggers_existing_shipment_notification(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForShipment($supplier);
        $order = $fulfillment->order;

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$fulfillment->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::ShipmentCreated->value,
        ]);
    }

    public function test_bulk_create_shipment_skips_local_fulfillments(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $company = $this->createChinaFulfillmentReadyForShipment($supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'CREATE_SHIPMENT',
            'fulfillment_ids' => [$local->id, $company->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);
    }

    private function createReadyLocalFulfillment(DeliveryType $deliveryType): Fulfillment
    {
        $fulfillment = $this->createLocalPendingFulfillment($deliveryType);
        $job = $fulfillment->warehouseJob;
        $this->assertInstanceOf(WarehouseJob::class, $job);

        $warehouse = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ] as $status) {
            $job = $warehouse->updateStatus($job, ['status' => $status->value]);
        }

        $fulfillment->refresh();

        return $fulfillment->fresh(['warehouseJob', 'order.user', 'order.deliveryOption']);
    }

    private function createLocalPendingFulfillment(DeliveryType $deliveryType): Fulfillment
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
        ]);

        return app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']))
            ->fresh(['warehouseJob', 'order.user']);
    }

    private function createChinaPendingFulfillment(): Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'order_from_china']);
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
        ]);

        $fulfillment = app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']));

        $fulfillment->forceFill(['strategy' => FulfillmentStrategy::China])->save();

        return $fulfillment->fresh(['warehouseJob', 'order.user']);
    }

    private function createChinaFulfillmentWithoutPurchase(): Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => null,
        ]);
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
        ]);

        return app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']))
            ->fresh(['warehouseJob', 'order.user', 'order.items']);
    }

    private function createChinaFulfillmentWithPurchase(Supplier $supplier): Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => $supplier->id,
        ]);
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
        ]);

        $fulfillment = app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product.supplier']));

        $this->assertSame(1, PurchaseOrder::query()->where('fulfillment_id', $fulfillment->id)->count());

        return $fulfillment->fresh(['warehouseJob', 'order.user', 'order.items']);
    }

    private function createChinaFulfillmentReadyToReceive(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentWithPurchase($supplier);
        $purchaseOrder = PurchaseOrder::query()->where('fulfillment_id', $fulfillment->id)->firstOrFail();

        app(ChinaWorkflowEngine::class)->recordSupplierResponse(
            $purchaseOrder,
            SupplierPoResponse::Accepted,
            null,
            Admin::factory()->create(),
        );

        return $fulfillment->fresh(['chinaWorkflowRecord', 'purchaseOrders.items', 'order.deliveryOption']);
    }

    private function createChinaFulfillmentReadyForQc(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentReadyToReceive($supplier);
        $purchaseOrder = PurchaseOrder::query()->where('fulfillment_id', $fulfillment->id)->firstOrFail();
        $items = $purchaseOrder->items->map(fn ($item) => [
            'purchase_order_item_id' => $item->id,
            'quantity' => $item->quantityOutstanding(),
        ])->all();

        app(ReceivingEngine::class)->receive(
            $purchaseOrder,
            ['items' => $items],
            Admin::factory()->create(),
        );

        return $fulfillment->fresh(['chinaWorkflowRecord', 'order']);
    }

    private function createChinaFulfillmentReadyForPacking(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentReadyForQc($supplier);

        app(ChinaWorkflowEngine::class)->recordQc(
            $fulfillment->order,
            ChinaQcStatus::Passed,
            null,
            Admin::factory()->create(),
        );

        return $fulfillment->fresh(['chinaWorkflowRecord', 'warehouseJob', 'order']);
    }

    private function createChinaFulfillmentReadyForExport(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentReadyForPacking($supplier);
        $job = $fulfillment->warehouseJob;
        $this->assertInstanceOf(WarehouseJob::class, $job);

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

        return $fulfillment->fresh(['chinaWorkflowRecord', 'warehouseJob', 'order.deliveryOption']);
    }

    private function createChinaFulfillmentPackedOnly(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentReadyForPacking($supplier);
        $job = $fulfillment->warehouseJob;
        $this->assertInstanceOf(WarehouseJob::class, $job);

        $warehouse = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
        ] as $status) {
            $job = $warehouse->updateStatus($job->fresh(), ['status' => $status->value]);
        }

        return $fulfillment->fresh(['chinaWorkflowRecord', 'warehouseJob', 'order.deliveryOption']);
    }

    private function createChinaCustomerAgentFulfillmentReadyForHandover(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentReadyForPacking($supplier);
        DeliveryOption::query()
            ->where('order_id', $fulfillment->order_id)
            ->update([
                'delivery_type' => DeliveryType::CustomerAgent,
                'agent_name' => 'Agent Asha',
                'agent_contact' => '+255700000001',
            ]);

        $job = $fulfillment->warehouseJob;
        $this->assertInstanceOf(WarehouseJob::class, $job);

        $warehouse = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
        ] as $status) {
            $job = $warehouse->updateStatus($job->fresh(), ['status' => $status->value]);
        }

        $order = $fulfillment->order->fresh(['deliveryOption']);
        $admin = Admin::factory()->create();
        $agentEngine = app(CustomerAgentWorkflowEngine::class);
        $agentEngine->bootstrap($order, $admin);
        $agentEngine->authorize($order, $admin, ['agent_company' => 'Asha Logistics']);

        return $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'order.user']);
    }

    private function createChinaFulfillmentReadyForShipment(Supplier $supplier): Fulfillment
    {
        $fulfillment = $this->createChinaFulfillmentReadyForExport($supplier);
        $admin = Admin::factory()->create();

        DeliveryOption::query()
            ->where('order_id', $fulfillment->order_id)
            ->update([
                'delivery_type' => DeliveryType::CompanyShipping,
                'shipping_method' => DeliveryShippingMethod::Air,
            ]);

        app(ChinaWorkflowEngine::class)->markExportReady(
            $fulfillment->order,
            $admin,
            [
                'commercial_invoice' => true,
                'packing_list' => true,
                'customs_docs' => true,
                'weight_confirmed' => true,
                'dimensions_confirmed' => true,
            ],
        );

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        return $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment', 'order.user']);
    }

    /**
     * @return array{0: Fulfillment, 1: User}
     */
    private function createHandoverReadyFulfillment(
        LastMileReceivingMethod $method,
        ?Supplier $supplier = null,
    ): array {
        $supplier ??= Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $fulfillment = $this->createChinaFulfillmentReadyForShipment($supplier);

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::InTransit,
            'shipped_at' => now()->subDay(),
        ]);

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        $user = $fulfillment->order?->user;
        $this->assertInstanceOf(User::class, $user);

        app(CompanyShippingReceivingChoiceService::class)->select(
            $fulfillment->order->fresh(['deliveryOption', 'fulfillment.shipment']),
            $user,
            $method,
        );

        return [$fulfillment->fresh(['order.deliveryOption', 'shipment', 'order.user']), $user];
    }

    public function test_bulk_mark_customer_collected_processes_multiple_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$first] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);
        [$second] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.skipped', 0);

        $first->refresh();
        $second->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $first->status);
        $this->assertSame(FulfillmentStatus::Delivered, $second->status);
    }

    public function test_bulk_mark_customer_delivered_processes_multiple_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$first] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::NegotiatedDelivery);
        [$second] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::NegotiatedDelivery);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_DELIVERED',
            'fulfillment_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_handover_supports_partial_success(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$ready] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);
        [$wrongMethod] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::NegotiatedDelivery);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$ready->id, $wrongMethod->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.failed', 0);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('succeeded', $results[(string) $ready->id]['status']);
        $this->assertSame('skipped', $results[(string) $wrongMethod->id]['status']);
        $this->assertSame('INVALID_METHOD', $results[(string) $wrongMethod->id]['reason_code']);
    }

    public function test_bulk_handover_skips_already_completed_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$completed] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);
        [$pending] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);

        app(CompanyShippingHandoverService::class)->completePickup($completed, $admin);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$completed->id, $pending->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 1);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('ALREADY_COMPLETED', $results[(string) $completed->id]['reason_code']);
    }

    public function test_bulk_handover_skips_wrong_receiving_method(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$deliveryChoice] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::NegotiatedDelivery);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$deliveryChoice->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.succeeded', 0)
            ->assertJsonPath('data.results.0.reason_code', 'INVALID_METHOD');
    }

    public function test_bulk_handover_reuses_completion_notifications(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$fulfillment, $user] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::CompanyHandoverCompletedPickup->value,
        ]);
    }

    public function test_bulk_handover_reuses_per_item_and_batch_audit(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        [$fulfillment] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$fulfillment->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CompanyShippingHandoverCompleted->value,
            'subject_id' => $fulfillment->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::FulfillmentBulkActionCompleted->value,
        ]);
    }

    public function test_bulk_handover_skips_local_and_customer_agent_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $local = $this->createLocalPendingFulfillment(DeliveryType::SelfPickup);
        $agent = $this->createChinaCustomerAgentFulfillmentReadyForHandover($supplier);
        [$company] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup, $supplier);

        $response = $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$local->id, $agent->id, $company->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.skipped', 2);

        $results = collect($response->json('data.results'))->keyBy('fulfillment_id');
        $this->assertSame('NOT_COMPANY_SHIPPING', $results[(string) $local->id]['reason_code']);
        $this->assertSame('NOT_COMPANY_SHIPPING', $results[(string) $agent->id]['reason_code']);
    }

    public function test_bulk_handover_requires_orders_fulfill_permission(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        [$fulfillment] = $this->createHandoverReadyFulfillment(LastMileReceivingMethod::SelfPickup);

        $this->postJson('/api/v1/admin/fulfillments/bulk-action', [
            'action_key' => 'MARK_CUSTOMER_COLLECTED',
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertForbidden();
    }
}
