<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\CustomerOrderProgressKey;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\LocalFulfillmentCompletionService;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Warehouse\WarehouseEngine;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalFulfillmentCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    /**
     * @return array{0: Order, 1: \App\Models\Fulfillment, 2: WarehouseJob}
     */
    private function makeReadyLocalOrder(DeliveryType $deliveryType = DeliveryType::SelfPickup): array
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
        $job = $fulfillment->fresh('warehouseJob')->warehouseJob;
        $this->assertNotNull($job);

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

        return [
            $order->fresh(['fulfillment.warehouseJob', 'deliveryOption']),
            $fulfillment->fresh(['warehouseJob', 'order.deliveryOption']),
            $job->fresh(),
        ];
    }

    public function test_admin_can_complete_self_pickup_local_order(): void
    {
        [$order, $fulfillment] = $this->makeReadyLocalOrder(DeliveryType::SelfPickup);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-local")
            ->assertOk()
            ->assertJsonPath('success', true);

        $fulfillment->refresh();
        $order->refresh();

        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);
        $this->assertNotNull($fulfillment->completed_at);
        $this->assertSame(OrderStatus::Delivered, $order->status);

        $progress = app(CustomerOrderProgressResolver::class)->resolve(
            $order->fresh(['fulfillment.warehouseJob', 'deliveryOption', 'payments'])
        );
        $this->assertSame(CustomerOrderProgressKey::Delivered->value, $progress['current_key']);
        $this->assertSame('Completed', $progress['current_label']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::LocalOrderCompletedPickup->value,
        ]);
    }

    public function test_admin_can_complete_delivery_arrangement_local_order(): void
    {
        [$order, $fulfillment] = $this->makeReadyLocalOrder(DeliveryType::NegotiatedDelivery);
        Sanctum::actingAs(Admin::factory()->create());

        app(LocalFulfillmentCompletionService::class)->complete($fulfillment);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'event_type' => NotificationEventType::LocalOrderCompletedDeliveryArrangement->value,
        ]);
    }

    public function test_china_fulfilment_cannot_use_local_completion_endpoint(): void
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

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-local")
            ->assertStatus(422);
    }

    public function test_local_completion_rejected_before_order_ready(): void
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
            'delivery_type' => DeliveryType::SelfPickup,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));
        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/fulfillments/{$fulfillment->id}/complete-local")
            ->assertStatus(422);
    }
}
