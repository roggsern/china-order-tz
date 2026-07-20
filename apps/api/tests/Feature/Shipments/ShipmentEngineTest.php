<?php

namespace Tests\Feature\Shipments;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TransportMode;
use App\Models\Admin;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Enums\WarehouseJobStatus;
use App\Services\Shipments\ShipmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: Fulfillment, 2: DeliveryOption}
     */
    private function makeReadyContext(
        DeliveryType $deliveryType,
        ?DeliveryShippingMethod $shippingMethod = null,
        DeliveryOptionStatus $deliveryStatus = DeliveryOptionStatus::Pending,
        array $productAttrs = ['fulfillment_source' => 'imported_from_china'],
    ): array {
        $user = User::factory()->create();
        $product = Product::factory()->create($productAttrs);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 40000,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 40000,
            'total_price' => 40000,
            'line_total' => 40000,
        ]);

        $fulfillment = Fulfillment::factory()->create([
            'order_id' => $order->id,
            'strategy' => $productAttrs['fulfillment_source'] === 'buy_from_tz'
                ? FulfillmentStrategy::Local
                : FulfillmentStrategy::China,
            'status' => FulfillmentStatus::ReadyForShipping,
            'started_at' => now(),
        ]);

        WarehouseJob::factory()->readyToShip()->create([
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
            'status' => WarehouseJobStatus::ReadyToShip,
        ]);

        $delivery = DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => $deliveryType,
            'shipping_method' => $shippingMethod,
            'delivery_status' => $deliveryStatus,
            'agent_name' => $deliveryType === DeliveryType::CustomerAgent ? 'Agent' : null,
            'agent_contact' => $deliveryType === DeliveryType::CustomerAgent ? '+255700000000' : null,
            'confirmed_at' => $deliveryStatus === DeliveryOptionStatus::Confirmed ? now() : null,
        ]);

        return [$order->fresh(['deliveryOption', 'fulfillment']), $fulfillment->fresh(), $delivery];
    }

    public function test_company_shipping_is_eligible(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );

        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson("/api/v1/admin/fulfillments/{$fulfillment->id}/shipment-eligibility")
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.transport_mode', 'air');
    }

    public function test_customer_agent_and_self_pickup_not_eligible(): void
    {
        [, $agentFulfillment] = $this->makeReadyContext(DeliveryType::CustomerAgent);
        [, $pickupFulfillment] = $this->makeReadyContext(
            DeliveryType::SelfPickup,
            null,
            DeliveryOptionStatus::Pending,
            ['fulfillment_source' => 'buy_from_tz'],
        );

        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson("/api/v1/admin/fulfillments/{$agentFulfillment->id}/shipment-eligibility")
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.reason', 'Customer Agent');

        $this->getJson("/api/v1/admin/fulfillments/{$pickupFulfillment->id}/shipment-eligibility")
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.reason', 'Self Pickup');
    }

    public function test_negotiated_requires_admin_confirmation(): void
    {
        [$order, $fulfillment] = $this->makeReadyContext(
            DeliveryType::NegotiatedDelivery,
            null,
            DeliveryOptionStatus::Pending,
            ['fulfillment_source' => 'buy_from_tz'],
        );

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}")
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/orders/{$order->id}/delivery-option/confirm-negotiated")
            ->assertOk()
            ->assertJsonPath('data.delivery_status', 'confirmed');

        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}")
            ->assertCreated()
            ->assertJsonPath('data.transport_mode', 'road')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_creates_shipment_for_company_shipping(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Sea,
        );

        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}", [
            'carrier_name' => 'Demo Carrier',
            'notes' => 'No label generated',
        ])->assertCreated()
            ->assertJsonPath('data.transport_mode', 'sea')
            ->assertJsonPath('data.carrier_name', 'Demo Carrier')
            ->assertJsonPath('data.status', 'pending');

        $this->assertMatchesRegularExpression(
            '/^COTZ-SHIP-\d{8}-\d{6}$/',
            (string) $fulfillment->fresh()->shipment->shipment_number,
        );
    }

    public function test_duplicate_shipment_prevented(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );

        Sanctum::actingAs(Admin::factory()->create());
        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}")->assertCreated();
        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}")->assertStatus(422);

        $this->assertSame(1, Shipment::query()->where('fulfillment_id', $fulfillment->id)->count());
    }

    public function test_direct_status_patch_is_prohibited(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );

        $shipment = app(ShipmentEngine::class)->createForFulfillment($fulfillment);
        Sanctum::actingAs(Admin::factory()->create());

        $this->patchJson("/api/v1/admin/shipments/{$shipment->id}/status", [
            'status' => 'booked',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_metadata_patch_allowed_without_status(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );

        $shipment = app(ShipmentEngine::class)->createForFulfillment($fulfillment);
        Sanctum::actingAs(Admin::factory()->create());

        $this->patchJson("/api/v1/admin/shipments/{$shipment->id}/status", [
            'carrier_name' => 'Demo Logistics',
        ])->assertOk()->assertJsonPath('data.carrier_name', 'Demo Logistics');
    }

    public function test_relationships(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );
        $shipment = app(ShipmentEngine::class)->createForFulfillment($fulfillment);

        $this->assertTrue($fulfillment->fresh()->shipment->is($shipment));
        $this->assertTrue($shipment->fulfillment->is($fulfillment));
        $this->assertSame(TransportMode::Air, $shipment->transport_mode);
        $this->assertSame(ShipmentLifecycleStatus::Pending, $shipment->status);
    }

    public function test_guest_and_customer_cannot_access_admin_shipments(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );

        $this->getJson('/api/v1/admin/shipments')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}")->assertUnauthorized();
    }

    public function test_admin_shipments_index(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );
        app(ShipmentEngine::class)->createForFulfillment($fulfillment);

        Sanctum::actingAs(Admin::factory()->create());
        $this->getJson('/api/v1/admin/shipments')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_requires_ready_for_shipping(): void
    {
        [, $fulfillment] = $this->makeReadyContext(
            DeliveryType::CompanyShipping,
            DeliveryShippingMethod::Air,
        );
        $fulfillment->update(['status' => FulfillmentStatus::Processing]);

        Sanctum::actingAs(Admin::factory()->create());
        $this->postJson("/api/v1/admin/shipments/create/{$fulfillment->id}")
            ->assertStatus(422);
    }
}
