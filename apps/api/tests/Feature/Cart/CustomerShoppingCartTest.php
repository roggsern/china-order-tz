<?php

namespace Tests\Feature\Cart;

use App\Enums\CartStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerShoppingCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_views_cart(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);

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
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', '50000.00')
            ->assertJsonPath('data.total', '50000.00')
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id);
    }

    public function test_customer_adds_item_to_cart_using_variant_price_and_inventory(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000, 20);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.unit_price', '15000.00')
            ->assertJsonPath('data.items.0.price_snapshot', '15000.00')
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.subtotal', '15000.00');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 15000,
        ]);
    }

    public function test_duplicate_variant_increments_quantity(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.subtotal', '30000.00')
            ->assertJsonPath('data.item_count', 3);

        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_customer_updates_item_quantity_with_put(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(12000, 40);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 12000,
            'price_snapshot' => 12000,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 4,
        ])->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.subtotal', '48000.00');
    }

    public function test_customer_removes_item_from_cart(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/cart/items/{$item->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.is_empty', true);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_customer_clears_cart(): void
    {
        $user = User::factory()->create();
        ['product' => $productA, 'variant' => $variantA] = CatalogCartFixture::purchasable();
        ['product' => $productB, 'variant' => $variantB] = CatalogCartFixture::purchasable(18000);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $productA->id,
            'product_variant_id' => $variantA->id,
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $productB->id,
            'product_variant_id' => $variantB->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/cart/clear')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.subtotal', '0.00');

        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_buy_now_preparation_requires_variant(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(45000, 20);

        $activeCart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        ['product' => $otherProduct, 'variant' => $otherVariant] = CatalogCartFixture::purchasable(1000);
        CartItem::factory()->create([
            'cart_id' => $activeCart->id,
            'product_id' => $otherProduct->id,
            'product_variant_id' => $otherVariant->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_type', 'buy_now')
            ->assertJsonPath('data.ready_for_checkout', true)
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', '90000.00')
            ->assertJsonPath('data.cart.status', CartStatus::CheckoutSession->value)
            ->assertJsonPath('data.cart.items.0.product_id', $product->id)
            ->assertJsonPath('data.cart.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.cart.items.0.quantity', 2);

        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->where('status', CartStatus::Active)->count());
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->where('status', CartStatus::CheckoutSession)->count());
        $this->assertSame(1, CartItem::query()->where('cart_id', $activeCart->id)->count());
    }

    public function test_variant_path_product_cannot_add_without_variant(): void
    {
        $user = User::factory()->create();
        ['product' => $product] = CatalogCartFixture::purchasable(15000, 10);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_simple_path_product_can_add_without_variant(): void
    {
        $user = User::factory()->create();
        $department = \App\Models\Department::factory()->create();
        $leaf = \App\Models\Category::factory()->forDepartment($department)->create([
            'parent_id' => null,
            'is_active' => true,
            'origin' => 'china',
        ]);
        $cpt = \App\Models\CatalogProductType::factory()->create([
            'subcategory_id' => $leaf->id,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'category_id' => $leaf->id,
            'catalog_product_type_id' => $cpt->id,
            'price' => 12500,
            'is_active' => true,
            'lifecycle_status' => 'active',
            'is_demo' => false,
        ]);
        \App\Models\Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 6,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.product_variant_id', null)
            ->assertJsonPath('data.items.0.unit_price', '12500.00');
    }

    public function test_cannot_add_when_no_retail_price(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'is_demo' => false,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => null,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 10,
            'reserved' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnprocessable();
    }

    public function test_cannot_add_more_than_available_inventory(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = CatalogCartFixture::purchasable(10000, 2);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_cannot_add_inactive_variant(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = CatalogCartFixture::purchasable();
        $variant->update(['is_active' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnprocessable();
    }

    public function test_guest_rejected(): void
    {
        ['variant' => $variant] = CatalogCartFixture::purchasable();

        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnauthorized();
        $this->postJson('/api/v1/cart/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        ['variant' => $variant] = CatalogCartFixture::purchasable();

        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_access_another_customers_cart_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable();

        $cart = Cart::factory()->create([
            'user_id' => $owner->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
        ]);

        Sanctum::actingAs($otherUser);

        $this->putJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 5,
        ])->assertNotFound();

        $this->deleteJson("/api/v1/cart/items/{$item->id}")->assertNotFound();
    }

    public function test_user_has_active_cart_relationship(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $this->assertTrue($user->activeCart()->whereKey($cart->id)->exists());
    }
}
