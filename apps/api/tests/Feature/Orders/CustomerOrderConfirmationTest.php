<?php

namespace Tests\Feature\Orders;

use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\ProductShippingOption;
use App\Models\User;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /orders/confirm is a compatibility façade over Checkout Session + Order Engine.
 */
class CustomerOrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerWithAddress(): User
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Customer',
        ]);

        DeliveryAddress::factory()->create([
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function seedPurchasableCart(User $user, float $price = 25000, int $qty = 2, array $itemOverrides = []): array
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable($price);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create(array_merge([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => $price,
            'price_snapshot' => $price,
            'currency' => 'TZS',
        ], $itemOverrides));

        return compact('product', 'variant', 'cart');
    }

    public function test_confirm_checkout_creates_order_via_order_engine(): void
    {
        $user = $this->createCustomerWithAddress();
        $this->seedPurchasableCart($user, 25000, 2);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.subtotal', '50000.00')
            ->assertJsonPath('data.summary.total', '50000.00')
            ->assertJsonPath('data.order.status', OrderStatus::PendingPayment->value);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment->value,
            'subtotal' => '50000.00',
            'total' => '50000.00',
        ]);
        $this->assertNotNull(Order::query()->value('checkout_session_id'));
        $this->assertNotNull(Order::query()->value('commerce_channel_id'));
    }

    public function test_order_items_created(): void
    {
        $user = $this->createCustomerWithAddress();
        ['product' => $product] = $this->seedPurchasableCart($user, 15000, 3);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])->assertCreated();

        $orderId = Order::query()->value('id');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => '15000.00',
            'total_price' => '45000.00',
        ]);

        $response->assertJsonPath('data.items.0.product_name', $product->name);
    }

    public function test_order_number_generated(): void
    {
        $user = $this->createCustomerWithAddress();
        $this->seedPurchasableCart($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])->assertCreated();

        $orderNumber = $response->json('data.order.order_number');

        $this->assertMatchesRegularExpression('/^COTZ-\d{8}-\d{6}$/', $orderNumber);
        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber]);
    }

    public function test_china_shipping_snapshot_saved_on_line_items(): void
    {
        $user = $this->createCustomerWithAddress();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(50000);
        $product->update([
            'fulfillment_source' => 'imported_from_china',
            'air_shipping_price' => 8000,
        ]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->air(8000)->create([
            'product_id' => $product->id,
            'notes' => 'Express air rate',
        ]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'price_snapshot' => 50000,
            'currency' => 'TZS',
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 8000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.shipping_method', 'air');

        $item = \App\Models\OrderItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(8000.0, (float) $item->getRawOriginal('shipping_price'));
        $this->assertSame(16000.0, (float) $item->getRawOriginal('shipping_subtotal'));
        $this->assertNull($item->delivery_status);

        $order = \App\Models\Order::query()->firstOrFail();
        $this->assertSame(16000.0, (float) $order->shipping_amount);
        $this->assertSame(116000.0, (float) $order->total);
    }

    public function test_dar_delivery_negotiation_saved(): void
    {
        $user = $this->createCustomerWithAddress();
        ['product' => $product] = $this->seedPurchasableCart($user, 35000, 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])
            ->assertCreated()
            ->assertJsonPath('data.items.0.delivery_status', 'To Be Negotiated')
            ->assertJsonMissingPath('data.items.0.shipping_method');

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'delivery_status' => 'To Be Negotiated',
            'shipping_method' => null,
        ]);
    }

    public function test_cart_emptied_after_order(): void
    {
        $user = $this->createCustomerWithAddress();
        ['cart' => $cart] = $this->seedPurchasableCart($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])->assertCreated();

        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_buy_now_checkout_session_cart_is_cleared(): void
    {
        $user = $this->createCustomerWithAddress();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);

        $sessionCart = Cart::factory()->checkoutSession()->create([
            'user_id' => $user->id,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $sessionCart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'price_snapshot' => 10000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])->assertCreated();

        $this->assertDatabaseMissing('carts', [
            'id' => $sessionCart->id,
        ]);
        $this->assertSame(0, CartItem::withTrashed()->where('cart_id', $sessionCart->id)->count());
    }

    public function test_guest_rejected(): void
    {
        $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson('/api/v1/orders/confirm', ['shipping_choice' => 'customer_agent'])->assertUnauthorized();
    }
}
