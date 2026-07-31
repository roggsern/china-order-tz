<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Models\Admin;
use App\Models\ChinaWorkflowRecord;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\FulfillmentStatusHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\FulfillmentOperationalReadModelBuilder;
use App\Services\Fulfillment\FulfillmentShipmentReconciliationService;
use App\Services\Tracking\TrackingEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentOperationalFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makePaidOrderWithFulfillment(
        array $productAttrs = [],
        FulfillmentStrategy $expectedStrategy = FulfillmentStrategy::Local,
    ): Fulfillment {
        $user = User::factory()->create();
        $product = Product::factory()->create($productAttrs);
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
        $this->assertSame($expectedStrategy, $fulfillment->strategy);

        return $fulfillment->fresh(['order', 'warehouseJob']);
    }

    private function advanceFulfillmentToShipped(Fulfillment $fulfillment): Fulfillment
    {
        $engine = app(FulfillmentEngine::class);

        $processing = $engine->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $ready = $engine->updateStatus($processing, [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        return $engine->updateStatus($ready, [
            'status' => FulfillmentStatus::Shipped->value,
        ]);
    }

    private function attachCompanyShippingDelivery(Fulfillment $fulfillment): void
    {
        DeliveryOption::factory()->companyShippingAir()->create([
            'order_id' => $fulfillment->order_id,
        ]);
    }

    private function attachCustomerAgentDelivery(Fulfillment $fulfillment): void
    {
        DeliveryOption::factory()->customerAgent()->create([
            'order_id' => $fulfillment->order_id,
        ]);
    }

    public function test_delivered_shipment_advances_shipped_negotiated_delivery_fulfilment(): void
    {
        $fulfillment = $this->advanceFulfillmentToShipped(
            $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']),
        );
        DeliveryOption::factory()->create([
            'order_id' => $fulfillment->order_id,
            'delivery_type' => DeliveryType::NegotiatedDelivery,
            'delivery_status' => \App\Enums\DeliveryOptionStatus::Confirmed,
        ]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment)->create([
            'status' => ShipmentLifecycleStatus::InTransit,
        ]);

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);

        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::Shipped->value,
            'to_status' => FulfillmentStatus::Delivered->value,
            'source' => FulfillmentStatusHistorySource::ShipmentReconciliation->value,
        ]);
    }

    public function test_cancelled_fulfilment_is_ignored_by_reconciliation(): void
    {
        $fulfillment = $this->advanceFulfillmentToShipped(
            $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']),
        );
        $this->attachCompanyShippingDelivery($fulfillment);

        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Cancelled->value,
        ]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::InTransit,
        ]);

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $this->assertSame(
            FulfillmentStatus::Cancelled,
            $fulfillment->fresh()->status,
        );
    }

    public function test_already_delivered_fulfilment_is_ignored_by_reconciliation(): void
    {
        $fulfillment = $this->advanceFulfillmentToShipped(
            $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']),
        );
        $this->attachCompanyShippingDelivery($fulfillment);

        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Delivered->value,
        ]);

        $historyCount = FulfillmentStatusHistory::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->count();

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::Delivered,
        ]);

        $result = app(FulfillmentShipmentReconciliationService::class)
            ->reconcileDeliveredShipment($shipment);

        $this->assertSame(FulfillmentStatus::Delivered, $result?->status);
        $this->assertSame(
            $historyCount,
            FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count(),
        );
    }

    public function test_invalid_transition_is_blocked_by_reconciliation(): void
    {
        $fulfillment = $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']);
        $this->attachCompanyShippingDelivery($fulfillment);

        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::Delivered,
        ]);

        $result = app(FulfillmentShipmentReconciliationService::class)
            ->reconcileDeliveredShipment($shipment);

        $this->assertNull($result);
        $this->assertSame(FulfillmentStatus::Processing, $fulfillment->fresh()->status);
    }

    public function test_customer_agent_delivery_skips_shipment_reconciliation(): void
    {
        $fulfillment = $this->advanceFulfillmentToShipped(
            $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'imported_from_china'], FulfillmentStrategy::China),
        );
        $this->attachCustomerAgentDelivery($fulfillment);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::Delivered,
        ]);

        $result = app(FulfillmentShipmentReconciliationService::class)
            ->reconcileDeliveredShipment($shipment);

        $this->assertNull($result);
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->fresh()->status);
    }

    public function test_status_change_creates_history(): void
    {
        $fulfillment = $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']);

        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);

        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::Pending->value,
            'to_status' => FulfillmentStatus::Processing->value,
            'source' => FulfillmentStatusHistorySource::System->value,
        ]);
    }

    public function test_failed_transition_creates_no_history(): void
    {
        $fulfillment = $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']);

        try {
            app(FulfillmentEngine::class)->updateStatus($fulfillment, [
                'status' => FulfillmentStatus::Delivered->value,
            ]);
            $this->fail('Expected validation exception.');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertSame(
            0,
            FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count(),
        );
    }

    public function test_china_operational_read_model_includes_china_workflow(): void
    {
        $fulfillment = $this->makePaidOrderWithFulfillment(
            ['fulfillment_source' => 'imported_from_china'],
            FulfillmentStrategy::China,
        );

        ChinaWorkflowRecord::query()->updateOrCreate(
            ['order_id' => $fulfillment->order_id],
            [
                'fulfillment_id' => $fulfillment->id,
                'stage' => 'export_ready',
                'qc_status' => 'passed',
            ],
        );

        $payload = app(FulfillmentOperationalReadModelBuilder::class)->build($fulfillment->fresh());

        $this->assertSame('china', $payload['fulfillment']['strategy']);
        $this->assertSame('export_ready', $payload['china']['stage']);
        $this->assertSame('passed', $payload['china']['qc_status']);
        $this->assertNotNull($payload['customer_progress']['current_key']);
    }

    public function test_local_operational_read_model_omits_china_section(): void
    {
        $fulfillment = $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']);

        $payload = app(FulfillmentOperationalReadModelBuilder::class)->build($fulfillment->fresh());

        $this->assertSame('local', $payload['fulfillment']['strategy']);
        $this->assertNull($payload['china']);
        $this->assertNotNull($payload['warehouse']['status']);
    }

    public function test_operational_read_model_handles_missing_shipment(): void
    {
        $fulfillment = $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']);

        $payload = app(FulfillmentOperationalReadModelBuilder::class)->build($fulfillment->fresh());

        $this->assertNull($payload['shipment']);
    }

    public function test_admin_operational_endpoint_returns_structured_payload(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $fulfillment = $this->makePaidOrderWithFulfillment(['fulfillment_source' => 'buy_from_tz']);

        $this->getJson("/api/v1/admin/fulfillments/{$fulfillment->id}/operational")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fulfillment.id', $fulfillment->id)
            ->assertJsonPath('data.order.order_number', $fulfillment->order->order_number)
            ->assertJsonStructure([
                'data' => [
                    'fulfillment' => ['status', 'strategy'],
                    'order' => ['order_number', 'customer', 'product'],
                    'warehouse' => ['status'],
                    'shipment',
                    'china',
                    'status_history',
                    'customer_progress' => ['current_key', 'current_label', 'steps'],
                ],
            ]);
    }
}
