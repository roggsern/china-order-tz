<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\CustomerOrderProgressKey;
use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\LastMileReceivingMethod;
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
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Tracking\TrackingEngine;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyShippingReceivingChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
    }

    /**
     * @return array{0: Order, 1: Shipment}
     */
    private function makeArrivedCompanyShippingOrder(bool $withArrival = true): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'imported_from_china']);
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
        $this->assertSame(FulfillmentStrategy::China, $fulfillment->strategy);

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

        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'shipping_method' => DeliveryShippingMethod::Air,
            'delivery_status' => DeliveryOptionStatus::Pending,
        ]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::InTransit,
            'shipped_at' => now()->subDay(),
        ]);

        if ($withArrival) {
            app(TrackingEngine::class)->recordEvent($shipment, [
                'event_type' => TrackingEventType::ArrivedDestination->value,
                'location' => 'Dar es Salaam',
            ]);
            $shipment->refresh();
        }

        return [$order->fresh(['deliveryOption', 'fulfillment.shipment', 'user']), $shipment->fresh()];
    }

    public function test_customer_can_select_self_pickup_after_arrival(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        Sanctum::actingAs($order->user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.receiving_choice.selected_method', LastMileReceivingMethod::SelfPickup->value)
            ->assertJsonPath('data.receiving_choice.can_select', false);

        $this->assertDatabaseHas('delivery_options', [
            'order_id' => $order->id,
            'last_mile_receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ]);

        $this->assertNotNull($order->fresh('deliveryOption')->deliveryOption?->last_mile_selected_at);
    }

    public function test_customer_can_select_delivery_after_arrival(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        Sanctum::actingAs($order->user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::NegotiatedDelivery->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.receiving_choice.selected_method', LastMileReceivingMethod::NegotiatedDelivery->value);

        $this->assertDatabaseHas('delivery_options', [
            'order_id' => $order->id,
            'last_mile_receiving_method' => LastMileReceivingMethod::NegotiatedDelivery->value,
        ]);
    }

    public function test_cannot_select_before_arrival(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder(withArrival: false);
        Sanctum::actingAs($order->user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);

        $this->assertDatabaseMissing('delivery_options', [
            'order_id' => $order->id,
            'last_mile_receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ]);
    }

    public function test_cannot_select_completed_order(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        Fulfillment::query()
            ->where('order_id', $order->id)
            ->update(['status' => FulfillmentStatus::Delivered->value]);

        Sanctum::actingAs($order->user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);
    }

    public function test_cannot_select_customer_agent_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        DeliveryOption::factory()->customerAgent()->create([
            'order_id' => $order->id,
            'delivery_status' => DeliveryOptionStatus::Pending,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'strategy' => FulfillmentStrategy::China,
            'status' => FulfillmentStatus::Shipped,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);
    }

    public function test_cannot_select_tz_local_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::SelfPickup,
            'delivery_status' => DeliveryOptionStatus::Pending,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'strategy' => FulfillmentStrategy::Local,
            'status' => FulfillmentStatus::Shipped,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);
    }

    public function test_notification_sent_after_self_pickup_selection(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        Sanctum::actingAs($order->user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::CompanyHandoverPickupRequested->value,
        ]);

        $notification = Notification::query()
            ->where('user_id', $order->user_id)
            ->where('event_type', NotificationEventType::CompanyHandoverPickupRequested->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('pickup request has been received', strtolower($notification->message));
    }

    public function test_notification_sent_after_delivery_selection(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        Sanctum::actingAs($order->user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::NegotiatedDelivery->value,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::CompanyHandoverDeliveryRequested->value,
        ]);

        $notification = Notification::query()
            ->where('user_id', $order->user_id)
            ->where('event_type', NotificationEventType::CompanyHandoverDeliveryRequested->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('contact our office', strtolower($notification->message));
    }

    public function test_tracking_updated_after_receiving_method_selection(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        $resolver = app(CustomerOrderProgressResolver::class);

        $arrivedProgress = $resolver->resolve($order->fresh(['payments', 'fulfillment.shipment', 'deliveryOption']));
        $this->assertSame(
            CustomerOrderProgressKey::ArrivedTanzania->value,
            $arrivedProgress['current_key'],
        );

        Sanctum::actingAs($order->user);
        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertOk();

        $selectedProgress = $resolver->resolve($order->fresh(['payments', 'fulfillment.shipment', 'deliveryOption']));
        $this->assertSame(
            CustomerOrderProgressKey::ChooseReceivingMethod->value,
            $selectedProgress['current_key'],
        );

        Sanctum::actingAs($order->user);
        $tracking = $this->getJson("/api/v1/orders/{$order->id}/tracking");
        $tracking->assertOk()
            ->assertJsonPath('data.progress.current_key', CustomerOrderProgressKey::ChooseReceivingMethod->value);

        $stepKeys = collect($tracking->json('data.progress.steps'))->pluck('key')->all();
        $this->assertSame([
            CustomerOrderProgressKey::OrderConfirmed->value,
            CustomerOrderProgressKey::Preparing->value,
            CustomerOrderProgressKey::Shipped->value,
            CustomerOrderProgressKey::ArrivedTanzania->value,
            CustomerOrderProgressKey::ChooseReceivingMethod->value,
            CustomerOrderProgressKey::Delivered->value,
        ], $stepKeys);
    }

    public function test_customer_cannot_select_for_another_customers_order(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertNotFound();
    }

    public function test_cannot_change_receiving_method_after_selection(): void
    {
        [$order] = $this->makeArrivedCompanyShippingOrder();
        Sanctum::actingAs($order->user);

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::SelfPickup->value,
        ])->assertOk();

        $this->postJson("/api/v1/orders/{$order->id}/receiving-method", [
            'receiving_method' => LastMileReceivingMethod::NegotiatedDelivery->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);
    }
}
