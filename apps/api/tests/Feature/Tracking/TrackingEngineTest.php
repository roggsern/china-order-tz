<?php

namespace Tests\Feature\Tracking;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Tracking\ShipmentStatusResolver;
use App\Services\Tracking\TrackingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrackingEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makeShipment(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'imported_from_china']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 50000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 50000,
            'total_price' => 50000,
            'line_total' => 50000,
        ]);
        $fulfillment = Fulfillment::factory()->create([
            'order_id' => $order->id,
            'strategy' => FulfillmentStrategy::China,
            'status' => FulfillmentStatus::ReadyForShipping,
            'started_at' => now(),
        ]);
        WarehouseJob::factory()->readyToShip()->create([
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
            'status' => WarehouseJobStatus::ReadyToShip,
        ]);
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'shipping_method' => DeliveryShippingMethod::Air,
            'delivery_status' => DeliveryOptionStatus::Pending,
        ]);

        $shipment = app(ShipmentEngine::class)->createForFulfillment($fulfillment);

        return [$user, $order->fresh(), $shipment];
    }

    public function test_records_tracking_event_and_syncs_shipment_status(): void
    {
        [, , $shipment] = $this->makeShipment();
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/shipments/{$shipment->id}/tracking", [
            'event_type' => 'collected',
            'location' => 'Dar warehouse',
            'description' => 'Parcel collected',
            'event_at' => now()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.event.event_type', 'collected')
            ->assertJsonPath('data.current_status', 'booked');

        $this->assertSame(ShipmentLifecycleStatus::Booked, $shipment->fresh()->status);
        $this->assertSame(1, ShipmentTrackingEvent::query()->where('shipment_id', $shipment->id)->count());
    }

    public function test_status_resolver_mapping(): void
    {
        $resolver = app(ShipmentStatusResolver::class);

        $this->assertSame(
            ShipmentLifecycleStatus::Pending,
            $resolver->resolveFromEventType(TrackingEventType::Booked),
        );
        $this->assertSame(
            ShipmentLifecycleStatus::InTransit,
            $resolver->resolveFromEventType(TrackingEventType::DepartedOrigin),
        );
        $this->assertSame(
            ShipmentLifecycleStatus::Arrived,
            $resolver->resolveFromEventType(TrackingEventType::WarehouseReceived),
        );
        $this->assertSame(
            ShipmentLifecycleStatus::Delivered,
            $resolver->resolveFromEventType(TrackingEventType::Delivered),
        );
    }

    public function test_chronological_validation(): void
    {
        [, , $shipment] = $this->makeShipment();
        $engine = app(TrackingEngine::class);

        $engine->recordEvent($shipment, [
            'event_type' => 'booked',
            'event_at' => now()->toIso8601String(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $engine->recordEvent($shipment->fresh(), [
            'event_type' => 'collected',
            'event_at' => now()->subDay()->toIso8601String(),
        ]);
    }

    public function test_append_only_prevents_update_and_delete(): void
    {
        [, , $shipment] = $this->makeShipment();
        $event = app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => 'booked',
        ]);

        $this->expectException(\RuntimeException::class);
        $event->update(['description' => 'changed']);
    }

    public function test_admin_tracking_index(): void
    {
        [, , $shipment] = $this->makeShipment();
        Sanctum::actingAs(Admin::factory()->create());

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => 'departed_origin',
            'location' => 'Shanghai',
        ]);

        $this->getJson("/api/v1/admin/shipments/{$shipment->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.current_status', 'in_transit')
            ->assertJsonPath('data.timeline.0.event_type', 'departed_origin');
    }

    public function test_customer_tracking_uses_shipment_events(): void
    {
        [$user, $order, $shipment] = $this->makeShipment();
        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => 'out_for_delivery',
            'location' => 'Dar es Salaam',
            'description' => 'Courier en route',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.source', 'shipment_tracking_events')
            ->assertJsonPath('data.current_status', 'in_transit')
            ->assertJsonPath('data.shipment_summary.shipment_number', $shipment->shipment_number)
            ->assertJsonPath('data.timeline.0.location', 'Dar es Salaam');
    }

    public function test_customer_ownership_enforced(): void
    {
        [, $order, $shipment] = $this->makeShipment();
        app(TrackingEngine::class)->recordEvent($shipment, ['event_type' => 'booked']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertNotFound();
    }

    public function test_relationships(): void
    {
        [, , $shipment] = $this->makeShipment();
        $event = app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => 'delivered',
            'location' => 'Customer address',
        ]);

        $this->assertTrue($shipment->fresh()->trackingEvents->first()->is($event));
        $this->assertTrue($event->shipment->is($shipment));
        $this->assertSame(ShipmentLifecycleStatus::Delivered, $shipment->fresh()->status);
    }

    public function test_guest_cannot_post_admin_tracking(): void
    {
        [, , $shipment] = $this->makeShipment();

        $this->postJson("/api/v1/admin/shipments/{$shipment->id}/tracking", [
            'event_type' => 'booked',
        ])->assertUnauthorized();
    }
}
