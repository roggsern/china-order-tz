<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartStatus;
use App\Enums\ShippingMethod;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCheckoutTest extends TestCase
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
            'street' => 'Sam Nujoma Road',
        ]);

        return $user;
    }

    private function ensureSimpleInventory(Product $product, int $quantity = 100): void
    {
        Inventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ],
        );
    }

    private function createActiveCartWithItem(User $user, Product $product, array $itemOverrides = []): Cart
    {
        $this->ensureSimpleInventory($product);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CartItem::factory()->create(array_merge([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $product->price,
            'price_snapshot' => $product->price,
        ], $itemOverrides));

        return $cart;
    }

    public function test_checkout_preparation(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 20000]);
        $this->createActiveCartWithItem($user, $product);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/prepare')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready_for_confirmation', true)
            ->assertJsonPath('data.customer.first_name', 'Jane')
            ->assertJsonPath('data.delivery_address.street', 'Sam Nujoma Road')
            ->assertJsonPath('data.subtotal', '40000.00')
            ->assertJsonPath('data.grand_total', '40000.00')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_get_checkout_preview(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 10000]);
        $this->createActiveCartWithItem($user, $product, ['quantity' => 1]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonPath('data.subtotal', '10000.00')
            ->assertJsonPath('data.ready_for_confirmation', true);
    }

    public function test_empty_cart_rejected(): void
    {
        $user = $this->createCustomerWithAddress();

        Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/prepare')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_missing_address_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->fromDar()->create();
        $this->createActiveCartWithItem($user, $product);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/prepare')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_address']);
    }

    public function test_china_shipping_summary(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromChina()->create([
            'price' => 50000,
            'air_shipping_price' => 8000,
            'sea_shipping_price' => 4000,
        ]);

        $this->createActiveCartWithItem($user, $product, [
            'quantity' => 2,
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 8000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/prepare')
            ->assertOk()
            ->assertJsonPath('data.items.0.source', 'China')
            ->assertJsonPath('data.items.0.shipping_method', 'air')
            ->assertJsonPath('data.items.0.shipping_price', '8000.00')
            ->assertJsonPath('data.items.0.shipping_subtotal', '16000.00')
            ->assertJsonPath('data.subtotal', '100000.00')
            ->assertJsonPath('data.shipping_summary.china_shipping_total', '16000.00')
            ->assertJsonPath('data.grand_total', '116000.00')
            ->assertJsonMissingPath('data.items.0.delivery_status');
    }

    public function test_dar_delivery_negotiation(): void
    {
        $user = $this->createCustomerWithAddress();
        $product = Product::factory()->fromDar()->create(['price' => 35000]);
        $this->createActiveCartWithItem($user, $product, ['quantity' => 1]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/prepare')
            ->assertOk()
            ->assertJsonPath('data.items.0.source', 'Dar')
            ->assertJsonPath('data.items.0.delivery_status', 'To Be Negotiated')
            ->assertJsonPath('data.shipping_summary.dar_delivery_status', 'To Be Negotiated')
            ->assertJsonMissingPath('data.items.0.shipping_method')
            ->assertJsonPath('data.grand_total', '35000.00');
    }

    public function test_mixed_cart_includes_both_shipping_summaries(): void
    {
        $user = $this->createCustomerWithAddress();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $chinaProduct = Product::factory()->fromChina()->create([
            'price' => 10000,
            'air_shipping_price' => 2000,
        ]);

        $darProduct = Product::factory()->fromDar()->create(['price' => 5000]);
        $this->ensureSimpleInventory($chinaProduct);
        $this->ensureSimpleInventory($darProduct);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $chinaProduct->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'price_snapshot' => 10000,
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 2000,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $darProduct->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'price_snapshot' => 5000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/prepare')
            ->assertOk()
            ->assertJsonPath('data.subtotal', '15000.00')
            ->assertJsonPath('data.shipping_summary.china_shipping_total', '2000.00')
            ->assertJsonPath('data.shipping_summary.dar_delivery_status', 'To Be Negotiated')
            ->assertJsonPath('data.grand_total', '17000.00');
    }

    public function test_guest_rejected(): void
    {
        $this->getJson('/api/v1/checkout')->assertUnauthorized();
        $this->postJson('/api/v1/checkout/prepare')->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/checkout')->assertUnauthorized();
        $this->postJson('/api/v1/checkout/prepare')->assertUnauthorized();
    }
}
