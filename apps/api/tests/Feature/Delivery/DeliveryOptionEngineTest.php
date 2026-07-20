<?php

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Delivery\DeliveryOptionEngine;
use App\Services\Delivery\DeliveryTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryOptionEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makePaidOrder(array $productAttrs = [], ?User $user = null): Order
    {
        $user ??= User::factory()->create();
        $product = Product::factory()->create($productAttrs);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 30000,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 30000,
            'total_price' => 30000,
            'line_total' => 30000,
        ]);

        return $order->fresh(['items.product.supplier', 'user']);
    }

    public function test_resolves_china_and_tanzania_markets(): void
    {
        $chinaOrder = $this->makePaidOrder(['fulfillment_source' => 'imported_from_china']);
        $tzOrder = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz']);

        $resolver = app(DeliveryTypeResolver::class);

        $this->assertSame('china', $resolver->resolveMarket($chinaOrder)->value);
        $this->assertSame('tanzania', $resolver->resolveMarket($tzOrder)->value);
    }

    public function test_china_company_shipping_requires_air_or_sea(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'imported_from_china'], $user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'company_shipping',
        ])->assertStatus(422)->assertJsonValidationErrors(['shipping_method']);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated()
            ->assertJsonPath('data.delivery_type', 'company_shipping')
            ->assertJsonPath('data.shipping_method', 'air')
            ->assertJsonPath('data.delivery_status', 'pending');
    }

    public function test_china_customer_agent_requires_agent_fields(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'imported_from_china'], $user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'customer_agent',
        ])->assertStatus(422);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'customer_agent',
            'agent_name' => 'Agent A',
            'agent_contact' => '+255712345678',
        ])->assertCreated()
            ->assertJsonPath('data.delivery_type', 'customer_agent')
            ->assertJsonPath('data.agent_name', 'Agent A')
            ->assertJsonPath('data.shipping_method', null);
    }

    public function test_tanzania_self_pickup_and_negotiated(): void
    {
        $user = User::factory()->create();
        $pickupOrder = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz'], $user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$pickupOrder->id}/delivery-option", [
            'delivery_type' => 'self_pickup',
            'notes' => 'Will collect Friday',
        ])->assertCreated()->assertJsonPath('data.delivery_type', 'self_pickup');

        $negotiated = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz'], $user);
        $this->postJson("/api/v1/orders/{$negotiated->id}/delivery-option", [
            'delivery_type' => 'negotiated_delivery',
        ])->assertCreated()->assertJsonPath('data.delivery_type', 'negotiated_delivery');
    }

    public function test_rejects_china_type_on_tanzania_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz'], $user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertStatus(422)->assertJsonValidationErrors(['delivery_type']);
    }

    public function test_rejects_unpaid_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'self_pickup',
        ])->assertStatus(422)->assertJsonValidationErrors(['order']);
    }

    public function test_show_returns_available_options_and_selection(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'imported_from_china'], $user);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}/delivery-option")
            ->assertOk()
            ->assertJsonPath('data.delivery_option', null)
            ->assertJsonPath('data.available.market', 'china');

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'company_shipping',
            'shipping_method' => 'sea',
        ])->assertCreated();

        $this->getJson("/api/v1/orders/{$order->id}/delivery-option")
            ->assertOk()
            ->assertJsonPath('data.delivery_option.shipping_method', 'sea');
    }

    public function test_patch_updates_and_confirms(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'imported_from_china'], $user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated();

        $this->patchJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_status' => 'confirmed',
        ])->assertOk()
            ->assertJsonPath('data.shipping_method', 'air')
            ->assertJsonPath('data.delivery_status', 'confirmed');

        $this->assertNotNull($order->fresh()->deliveryOption->confirmed_at);

        $this->patchJson("/api/v1/orders/{$order->id}/delivery-option", [
            'shipping_method' => 'sea',
        ])->assertStatus(422)->assertJsonValidationErrors(['shipping_method']);
    }

    public function test_order_has_one_delivery_option_relationship(): void
    {
        $order = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz']);
        $option = DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::SelfPickup,
        ]);

        $this->assertTrue($order->fresh()->deliveryOption->is($option));
        $this->assertTrue($option->order->is($order));
    }

    public function test_ownership_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz'], $owner);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/orders/{$order->id}/delivery-option")->assertNotFound();
        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'self_pickup',
        ])->assertNotFound();
    }

    public function test_does_not_modify_order_totals(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'imported_from_china'], $user);
        $totalBefore = (string) $order->total;
        $shippingBefore = (string) $order->shipping_amount;

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/delivery-option", [
            'delivery_type' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated();

        $order->refresh();
        $this->assertSame($totalBefore, (string) $order->total);
        $this->assertSame($shippingBefore, (string) $order->shipping_amount);
    }

    public function test_engine_select_via_di(): void
    {
        $user = User::factory()->create();
        $order = $this->makePaidOrder(['fulfillment_source' => 'buy_from_tz'], $user);

        $option = app(DeliveryOptionEngine::class)->select($user, $order, [
            'delivery_type' => 'negotiated_delivery',
            'notes' => 'Call before delivery',
        ]);

        $this->assertSame(DeliveryType::NegotiatedDelivery, $option->delivery_type);
        $this->assertSame(DeliveryOptionStatus::Pending, $option->delivery_status);
        $this->assertNull($option->shipping_method);
    }
}
