<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ActivityEventType;
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
use App\Models\Admin;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\CompanyShippingHandoverService;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Orders\CompanyShippingReceivingChoiceService;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Tracking\TrackingEngine;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyShippingHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
    }

    /**
     * @return array{0: Order, 1: Fulfillment}
     */
    private function makeHandoverReadyOrder(LastMileReceivingMethod $method): array
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

        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::ArrivedDestination->value,
            'location' => 'Dar es Salaam',
        ]);

        Sanctum::actingAs($user);
        app(CompanyShippingReceivingChoiceService::class)->select(
            $order->fresh(['deliveryOption', 'fulfillment.shipment']),
            $user,
            $method,
        );

        return [
            $order->fresh(['deliveryOption', 'fulfillment.shipment', 'user']),
            $fulfillment->fresh(['order.deliveryOption', 'shipment']),
        ];
    }

    public function test_pickup_completion_works(): void
    {
        [$order, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::SelfPickup);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-company-handover-pickup")
            ->assertOk()
            ->assertJsonPath('success', true);

        $fulfillment->refresh();
        $order->refresh();

        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);
        $this->assertNotNull($fulfillment->completed_at);
        $this->assertSame(OrderStatus::Delivered, $order->status);
    }

    public function test_delivery_completion_works(): void
    {
        [, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::NegotiatedDelivery);
        $admin = Admin::factory()->create();

        app(CompanyShippingHandoverService::class)->completeDelivery($fulfillment, $admin);

        $fulfillment->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);
        $this->assertNotNull($fulfillment->completed_at);
    }

    public function test_cannot_complete_before_customer_choice(): void
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
        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);

        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'shipping_method' => DeliveryShippingMethod::Air,
        ]);

        $shipment = Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::Arrived,
            'arrived_at' => now(),
        ]);

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-company-handover-pickup")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);
    }

    public function test_cannot_complete_before_arrival(): void
    {
        [$order, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::SelfPickup);
        Shipment::query()->where('fulfillment_id', $fulfillment->id)->update(['arrived_at' => null]);

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-company-handover-pickup")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipment']);
    }

    public function test_cannot_complete_customer_agent_order(): void
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
            'last_mile_receiving_method' => LastMileReceivingMethod::SelfPickup,
        ]);

        $fulfillment = Fulfillment::factory()->create([
            'order_id' => $order->id,
            'strategy' => FulfillmentStrategy::China,
            'status' => FulfillmentStatus::Shipped,
        ]);

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-company-handover-pickup")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fulfillment']);
    }

    public function test_cannot_complete_tz_local_order(): void
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
            'last_mile_receiving_method' => LastMileReceivingMethod::SelfPickup,
        ]);

        $fulfillment = Fulfillment::factory()->create([
            'order_id' => $order->id,
            'strategy' => FulfillmentStrategy::Local,
            'status' => FulfillmentStatus::Shipped,
        ]);

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-company-handover-pickup")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fulfillment']);
    }

    public function test_notification_sent_after_pickup_completion(): void
    {
        [$order, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::SelfPickup);
        $admin = Admin::factory()->create();

        app(CompanyShippingHandoverService::class)->completePickup($fulfillment, $admin);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::CompanyHandoverCompletedPickup->value,
        ]);

        $notification = Notification::query()
            ->where('user_id', $order->user_id)
            ->where('event_type', NotificationEventType::CompanyHandoverCompletedPickup->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('collected successfully', strtolower($notification->message));
    }

    public function test_notification_sent_after_delivery_completion(): void
    {
        [$order, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::NegotiatedDelivery);
        $admin = Admin::factory()->create();

        app(CompanyShippingHandoverService::class)->completeDelivery($fulfillment, $admin);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::CompanyHandoverCompletedDelivery->value,
        ]);

        $notification = Notification::query()
            ->where('user_id', $order->user_id)
            ->where('event_type', NotificationEventType::CompanyHandoverCompletedDelivery->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('delivered successfully', strtolower($notification->message));
    }

    public function test_tracking_completed_after_handover(): void
    {
        [$order, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::SelfPickup);
        $resolver = app(CustomerOrderProgressResolver::class);

        $before = $resolver->resolve($order->fresh(['payments', 'fulfillment.shipment', 'deliveryOption']));
        $this->assertSame(
            CustomerOrderProgressKey::ChooseReceivingMethod->value,
            $before['current_key'],
        );

        app(CompanyShippingHandoverService::class)->completePickup(
            $fulfillment,
            Admin::factory()->create(),
        );

        $after = $resolver->resolve($order->fresh(['payments', 'fulfillment.shipment', 'deliveryOption']));
        $this->assertSame(CustomerOrderProgressKey::Delivered->value, $after['current_key']);
        $this->assertSame('Completed', $after['current_label']);
    }

    public function test_activity_log_entry_created(): void
    {
        [$order, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::SelfPickup);
        $admin = Admin::factory()->create();

        app(CompanyShippingHandoverService::class)->completePickup($fulfillment, $admin);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CompanyShippingHandoverCompleted->value,
            'actor_id' => $admin->id,
            'subject_id' => $fulfillment->id,
        ]);
    }

    public function test_pickup_completion_rejects_delivery_method_order(): void
    {
        [, $fulfillment] = $this->makeHandoverReadyOrder(LastMileReceivingMethod::NegotiatedDelivery);
        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-company-handover-pickup")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['receiving_method']);
    }
}
