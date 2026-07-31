<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\CustomerOrderProgressKey;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Enums\WarehouseJobStatus;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\FulfillmentStatusHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Tracking\TrackingEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentShipmentDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return array{0: Fulfillment, 1: Shipment}
     */
    private function makeReadyForShippingShipment(
        DeliveryType $deliveryType,
        DeliveryOptionStatus $deliveryStatus = DeliveryOptionStatus::Pending,
        ?DeliveryShippingMethod $shippingMethod = DeliveryShippingMethod::Air,
        array $productAttrs = ['fulfillment_source' => 'buy_from_tz'],
    ): array {
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
        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        WarehouseJob::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->update(['status' => WarehouseJobStatus::ReadyToShip->value]);

        if ($deliveryType === DeliveryType::CustomerAgent) {
            DeliveryOption::factory()->customerAgent()->create([
                'order_id' => $order->id,
                'delivery_status' => $deliveryStatus,
            ]);
        } else {
            DeliveryOption::factory()->create([
                'order_id' => $order->id,
                'delivery_type' => $deliveryType,
                'shipping_method' => $shippingMethod,
                'delivery_status' => $deliveryStatus,
            ]);
        }

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::Pending,
        ]);

        return [$fulfillment->fresh(['order.deliveryOption']), $shipment];
    }

    public function test_departed_origin_advances_company_shipping_fulfilment_to_shipped(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(DeliveryType::CompanyShipping);

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::DepartedOrigin->value,
            'location' => 'Dar warehouse',
        ]);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->status);
        $this->assertSame(ShipmentLifecycleStatus::InTransit, $shipment->fresh()->status);

        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::ReadyForShipping->value,
            'to_status' => FulfillmentStatus::Shipped->value,
            'source' => FulfillmentStatusHistorySource::ShipmentDispatch->value,
        ]);
    }

    public function test_departed_origin_advances_negotiated_delivery_fulfilment_to_shipped(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(
            DeliveryType::NegotiatedDelivery,
            DeliveryOptionStatus::Confirmed,
            null,
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::DepartedOrigin->value,
        ]);

        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->fresh()->status);
    }

    public function test_customer_agent_delivery_does_not_dispatch_through_shipment_tracking(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(
            DeliveryType::CustomerAgent,
            DeliveryOptionStatus::Pending,
            null,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::DepartedOrigin->value,
        ]);

        $this->assertSame(FulfillmentStatus::ReadyForShipping, $fulfillment->fresh()->status);
        $this->assertDatabaseMissing('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'source' => FulfillmentStatusHistorySource::ShipmentDispatch->value,
        ]);
    }

    public function test_delivered_tracking_completes_negotiated_delivery_fulfilment_from_ready_for_shipping(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(
            DeliveryType::NegotiatedDelivery,
            DeliveryOptionStatus::Confirmed,
            null,
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);

        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::ReadyForShipping->value,
            'to_status' => FulfillmentStatus::Shipped->value,
            'source' => FulfillmentStatusHistorySource::ShipmentDispatch->value,
        ]);

        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::Shipped->value,
            'to_status' => FulfillmentStatus::Delivered->value,
            'source' => FulfillmentStatusHistorySource::ShipmentReconciliation->value,
        ]);
    }

    public function test_delivered_tracking_does_not_auto_complete_company_shipping_fulfilment(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(
            DeliveryType::CompanyShipping,
            DeliveryOptionStatus::Pending,
            DeliveryShippingMethod::Air,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->status);

        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::ReadyForShipping->value,
            'to_status' => FulfillmentStatus::Shipped->value,
            'source' => FulfillmentStatusHistorySource::ShipmentDispatch->value,
        ]);

        $this->assertDatabaseMissing('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'source' => FulfillmentStatusHistorySource::ShipmentReconciliation->value,
        ]);
    }

    public function test_duplicate_dispatch_does_not_create_duplicate_history(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(DeliveryType::CompanyShipping);
        $engine = app(TrackingEngine::class);

        $engine->recordEvent($shipment, [
            'event_type' => TrackingEventType::DepartedOrigin->value,
        ]);

        $historyCount = FulfillmentStatusHistory::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->where('source', FulfillmentStatusHistorySource::ShipmentDispatch->value)
            ->count();

        $engine->recordEvent($shipment->fresh(), [
            'event_type' => TrackingEventType::OutForDelivery->value,
        ]);

        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->fresh()->status);
        $this->assertSame(
            $historyCount,
            FulfillmentStatusHistory::query()
                ->where('fulfillment_id', $fulfillment->id)
                ->where('source', FulfillmentStatusHistorySource::ShipmentDispatch->value)
                ->count(),
        );
    }

    public function test_customer_progress_follows_dispatch_and_delivery(): void
    {
        [$fulfillment, $shipment] = $this->makeReadyForShippingShipment(DeliveryType::CompanyShipping);
        $order = $fulfillment->order;
        $resolver = app(CustomerOrderProgressResolver::class);

        $readyProgress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments', 'deliveryOption']));
        $this->assertSame(CustomerOrderProgressKey::Preparing, CustomerOrderProgressKey::from($readyProgress['current_key']));

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::DepartedOrigin->value,
        ]);

        $shippedProgress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments', 'deliveryOption']));
        $this->assertSame(CustomerOrderProgressKey::Shipped, CustomerOrderProgressKey::from($shippedProgress['current_key']));

        app(TrackingEngine::class)->recordEvent($shipment->fresh(), [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $afterDeliveredTracking = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments', 'deliveryOption']));
        $this->assertSame(CustomerOrderProgressKey::Shipped, CustomerOrderProgressKey::from($afterDeliveredTracking['current_key']));

        app(TrackingEngine::class)->recordEvent($shipment->fresh(), [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        $arrivedProgress = $resolver->resolve($order->fresh(['fulfillment.warehouseJob', 'shipments', 'deliveryOption', 'fulfillment.shipment']));
        $this->assertSame(CustomerOrderProgressKey::ArrivedTanzania, CustomerOrderProgressKey::from($arrivedProgress['current_key']));
    }
}
