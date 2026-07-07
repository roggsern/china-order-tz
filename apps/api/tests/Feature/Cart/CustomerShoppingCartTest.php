<?php

namespace Tests\Feature\Cart;

use App\Enums\CartStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerShoppingCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_views_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 25000]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.subtotal', '50000.00')
            ->assertJsonPath('data.total', '50000.00')
            ->assertJsonPath('data.items.0.product_id', $product->id);
    }

    public function test_customer_adds_item_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 15000]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.unit_price', '15000.00')
            ->assertJsonPath('data.items.0.subtotal', '15000.00');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_duplicate_product_increments_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.subtotal', '30000.00');

        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_customer_updates_item_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 12000]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 12000,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 4,
        ])->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.subtotal', '48000.00');
    }

    public function test_customer_removes_item_from_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/cart/items/{$item->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertSoftDeleted('cart_items', ['id' => $item->id]);
    }

    public function test_customer_clears_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CartItem::factory()->count(2)->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.subtotal', '0.00');

        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_buy_now_preparation(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 45000]);

        $activeCart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CartItem::factory()->create([
            'cart_id' => $activeCart->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/buy-now', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_type', 'buy_now')
            ->assertJsonPath('data.ready_for_checkout', true)
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.subtotal', '90000.00')
            ->assertJsonPath('data.cart.status', CartStatus::CheckoutSession->value)
            ->assertJsonPath('data.cart.items.0.product_id', $product->id)
            ->assertJsonPath('data.cart.items.0.quantity', 2);

        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->where('status', CartStatus::Active)->count());
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->where('status', CartStatus::CheckoutSession)->count());
        $this->assertSame(1, CartItem::query()->where('cart_id', $activeCart->id)->count());
    }

    public function test_cannot_add_inactive_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inactive()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_cannot_add_deleted_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $product->delete();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_guest_rejected(): void
    {
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/cart/items', [
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ])->assertUnauthorized();
        $this->postJson('/api/v1/cart/buy-now', [
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/cart/items', [
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ])->assertUnauthorized();
        $this->postJson('/api/v1/cart/buy-now', [
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_access_another_customers_cart_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create();

        $cart = Cart::factory()->create([
            'user_id' => $owner->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($otherUser);

        $this->patchJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 5,
        ])->assertNotFound();

        $this->deleteJson("/api/v1/cart/items/{$item->id}")->assertNotFound();
    }
}
