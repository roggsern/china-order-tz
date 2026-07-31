<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\AgentPickupStatus;
use App\Enums\ChinaQcStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\SupplierPoResponse;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Warehouse\WarehouseEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentRc1HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return array{0: Order, 1: Fulfillment}
     */
    private function makeShippedLocalOrder(DeliveryType $deliveryType = DeliveryType::SelfPickup): array
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

        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);

        return [$order->fresh(['deliveryOption']), $fulfillment->fresh(['order.deliveryOption'])];
    }

    /**
     * @return array{0: Order, 1: Fulfillment}
     */
    private function makeShippedCompanyShippingOrder(): array
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
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'shipping_method' => DeliveryShippingMethod::Air,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Shipped->value,
        ]);

        Shipment::factory()->forFulfillment($fulfillment->fresh())->create([
            'status' => ShipmentLifecycleStatus::InTransit,
            'shipped_at' => now()->subDay(),
            'arrived_at' => now(),
        ]);

        return [$order->fresh(['deliveryOption']), $fulfillment->fresh(['order.deliveryOption', 'shipment'])];
    }

    /**
     * @return array{0: Order, 1: Fulfillment}
     */
    private function makePackedCustomerAgentOrder(): array
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
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CustomerAgent,
            'shipping_method' => DeliveryShippingMethod::Air,
            'agent_name' => 'Agent Asha',
            'agent_contact' => '+255700000001',
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product']));
        $this->assertSame(FulfillmentStrategy::China, $fulfillment->strategy);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemId = $po->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $job = WarehouseJob::query()->where('fulfillment_id', $fulfillment->id)->firstOrFail();
        $wh = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
        ] as $status) {
            $wh->updateStatus($job->fresh(), ['status' => $status->value]);
        }

        return [$order->fresh(['deliveryOption']), $fulfillment->fresh(['order.deliveryOption', 'warehouseJob'])];
    }

    public function test_admin_cannot_patch_delivered_for_local_order(): void
    {
        [, $fulfillment] = $this->makeShippedLocalOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'status' => FulfillmentStatus::Delivered->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_cannot_patch_delivered_for_company_shipping(): void
    {
        [, $fulfillment] = $this->makeShippedCompanyShippingOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'status' => FulfillmentStatus::Delivered->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_cannot_patch_delivered_for_customer_agent(): void
    {
        [, $fulfillment] = $this->makePackedCustomerAgentOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'status' => FulfillmentStatus::Delivered->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_patch_non_terminal_status(): void
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
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'status' => FulfillmentStatus::Processing->value,
        ])->assertOk()
            ->assertJsonPath('data.status', FulfillmentStatus::Processing->value);
    }

    public function test_legacy_agent_handoff_cannot_bypass_workflow(): void
    {
        [$order] = $this->makePackedCustomerAgentOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/agent-handoff", [
            'agent_name' => 'Agent Asha',
            'agent_contact' => '+255700000001',
            'evidence' => 'Signed pickup sheet #1',
        ])->assertStatus(422);
    }

    public function test_legacy_agent_handoff_delegates_to_authoritative_engine(): void
    {
        [$order] = $this->makePackedCustomerAgentOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/bootstrap")->assertOk();
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize", [
            'agent_company' => 'Asha Logistics',
        ])->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/agent-handoff", [
            'agent_name' => 'Agent Asha',
            'agent_contact' => '+255700000001',
            'evidence' => 'Signed pickup sheet #1',
        ])->assertOk()
            ->assertJsonPath('data.pickup_status', AgentPickupStatus::HandoverCompleted->value);

        $fulfillment = $order->fresh()->fulfillment;
        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);
        $this->assertNotNull($fulfillment->completed_at);
    }
}
