<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\FulfillmentStrategy;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Enums\WarehouseJobStatus;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Tracking\TrackingEngine;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentShipmentArrivalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
    }

    /**
     * @return array{0: Fulfillment, 1: Shipment}
     */
    private function makeShippedShipment(
        DeliveryType $deliveryType,
        FulfillmentStrategy $strategy = FulfillmentStrategy::Local,
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
        $this->assertSame($strategy, $fulfillment->strategy);

        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);

        WarehouseJob::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->update(['status' => WarehouseJobStatus::ReadyToShip->value]);

        if ($deliveryType === DeliveryType::CustomerAgent) {
            DeliveryOption::factory()->customerAgent()->create([
                'order_id' => $order->id,
                'delivery_status' => DeliveryOptionStatus::Pending,
            ]);
        } else {
            DeliveryOption::factory()->create([
                'order_id' => $order->id,
                'delivery_type' => $deliveryType,
                'shipping_method' => $deliveryType === DeliveryType::CompanyShipping
                    ? DeliveryShippingMethod::Air
                    : null,
                'delivery_status' => DeliveryOptionStatus::Pending,
            ]);
        }

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::InTransit,
            'shipped_at' => now()->subDay(),
        ]);

        return [$fulfillment->fresh(['order.deliveryOption', 'order.user']), $shipment];
    }

    public function test_china_company_shipping_arrival_sets_arrived_at(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::CompanyShipping,
            FulfillmentStrategy::China,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        $shipment->refresh();
        $this->assertSame(ShipmentLifecycleStatus::Arrived, $shipment->status);
        $this->assertNotNull($shipment->arrived_at);
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->fresh()->status);
    }

    public function test_china_company_shipping_arrival_does_not_complete_fulfilment(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::CompanyShipping,
            FulfillmentStrategy::China,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::WarehouseReceived->value,
            'location' => 'Dar es Salaam warehouse',
        ]);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->status);
        $this->assertNull($fulfillment->completed_at);

        $this->assertDatabaseMissing('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'to_status' => FulfillmentStatus::Delivered->value,
        ]);
    }

    public function test_china_company_shipping_delivered_tracking_does_not_auto_complete(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::CompanyShipping,
            FulfillmentStrategy::China,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->status);
        $this->assertDatabaseMissing('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'source' => FulfillmentStatusHistorySource::ShipmentReconciliation->value,
        ]);
    }

    public function test_negotiated_delivery_delivered_tracking_still_auto_completes(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::NegotiatedDelivery,
            FulfillmentStrategy::Local,
            ['fulfillment_source' => 'buy_from_tz'],
        );

        DeliveryOption::query()
            ->where('order_id', $fulfillment->order_id)
            ->update(['delivery_status' => DeliveryOptionStatus::Confirmed->value]);

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
        ]);

        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->fresh()->status);
        $this->assertDatabaseHas('fulfillment_status_histories', [
            'fulfillment_id' => $fulfillment->id,
            'from_status' => FulfillmentStatus::Shipped->value,
            'to_status' => FulfillmentStatus::Delivered->value,
            'source' => FulfillmentStatusHistorySource::ShipmentReconciliation->value,
        ]);
    }

    public function test_customer_agent_arrival_does_not_send_tanzania_arrival_notification(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::CustomerAgent,
            FulfillmentStrategy::China,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        $this->assertNotNull($shipment->fresh()->arrived_at);
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->fresh()->status);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $fulfillment->order?->user_id,
            'event_type' => NotificationEventType::ShipmentArrivedTanzania->value,
        ]);
    }

    public function test_china_company_shipping_arrival_notification_is_idempotent(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::CompanyShipping,
            FulfillmentStrategy::China,
            ['fulfillment_source' => 'imported_from_china'],
        );
        $engine = app(TrackingEngine::class);

        $engine->recordEvent($shipment, [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        $firstArrivedAt = $shipment->fresh()->arrived_at;
        $this->assertNotNull($firstArrivedAt);

        $engine->recordEvent($shipment->fresh(), [
            'event_type' => TrackingEventType::WarehouseReceived->value,
            'location' => 'Dar warehouse',
            'event_at' => now()->addHour()->toIso8601String(),
        ]);

        $this->assertTrue($firstArrivedAt->equalTo($shipment->fresh()->arrived_at));

        $this->assertSame(
            1,
            Notification::query()
                ->where('user_id', $fulfillment->order?->user_id)
                ->where('event_type', NotificationEventType::ShipmentArrivedTanzania->value)
                ->count(),
        );
    }

    public function test_china_company_shipping_arrival_sends_dedicated_notification(): void
    {
        [$fulfillment, $shipment] = $this->makeShippedShipment(
            DeliveryType::CompanyShipping,
            FulfillmentStrategy::China,
            ['fulfillment_source' => 'imported_from_china'],
        );

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $fulfillment->order?->user_id,
            'event_type' => NotificationEventType::ShipmentArrivedTanzania->value,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $fulfillment->order?->user_id,
            'event_type' => NotificationEventType::TrackingUpdated->value,
        ]);
    }
}
