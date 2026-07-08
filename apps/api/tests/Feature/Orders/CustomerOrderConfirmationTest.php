<?php

namespace Tests\Feature\Orders;

use App\Enums\CartStatus;
use App\Enums\ShippingMethod;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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

    private function createActiveCart(User $user, Product $product, array $itemOverrides = []): Cart
    {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CartItem::factory()->create(array_merge([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $product->price,
        ], $itemOverrides));

        return $cart;
    }

    public function test_confirm_checkout_creates_order(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 25000]);
        $this->createActiveCart($user, $product);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.subtotal', '50000.00')
            ->assertJsonPath('data.summary.total', '50000.00')
            ->assertJsonPath('data.order.status', 'pending');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'subtotal' => '50000.00',
            'total' => '50000.00',
        ]);
    }

    public function test_order_items_created(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 15000]);
        $this->createActiveCart($user, $product, ['quantity' => 3]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm')->assertCreated();

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
        $product = Product::factory()->fromDar()->create();
        $this->createActiveCart($user, $product);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm')->assertCreated();

        $orderNumber = $response->json('data.order.order_number');

        $this->assertMatchesRegularExpression('/^COTZ-\d{4}-\d{6}$/', $orderNumber);
        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber]);
    }

    public function test_china_shipping_snapshot_saved(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromChina()->create([
            'price' => 50000,
            'air_shipping_price' => 8000,
        ]);

        $this->createActiveCart($user, $product, [
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 8000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm')
            ->assertCreated()
            ->assertJsonPath('data.items.0.shipping_method', 'air')
            ->assertJsonPath('data.items.0.shipping_price', '8000.00')
            ->assertJsonPath('data.items.0.shipping_subtotal', '16000.00')
            ->assertJsonPath('data.summary.shipping', '16000.00')
            ->assertJsonPath('data.summary.total', '116000.00');

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'shipping_method' => 'air',
            'shipping_price' => '8000.00',
            'shipping_subtotal' => '16000.00',
            'delivery_status' => null,
        ]);
    }

    public function test_dar_delivery_negotiation_saved(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 35000]);
        $this->createActiveCart($user, $product, ['quantity' => 1]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm')
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
        $product = Product::factory()->fromDar()->create();
        $cart = $this->createActiveCart($user, $product);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm')->assertCreated();

        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_checkout_session_removed_after_order(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 10000]);

        $sessionCart = Cart::factory()->checkoutSession()->create([
            'user_id' => $user->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $sessionCart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm')->assertCreated();

        $this->assertSoftDeleted('carts', [
            'id' => $sessionCart->id,
        ]);
        $this->assertSame(0, CartItem::query()->where('cart_id', $sessionCart->id)->count());
    }

    public function test_guest_rejected(): void
    {
        $this->postJson('/api/v1/orders/confirm')->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson('/api/v1/orders/confirm')->assertUnauthorized();
    }
}
