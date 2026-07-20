<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_starts_checkout_session(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(20000);

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
            'unit_price' => 20000,
            'price_snapshot' => 20000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.subtotal', '40000.00')
            ->assertJsonPath('data.shipping_total', '0.00')
            ->assertJsonPath('data.tax_total', '0.00')
            ->assertJsonPath('data.discount_total', '0.00')
            ->assertJsonPath('data.grand_total', '40000.00')
            ->assertJsonPath('data.cart_id', $cart->id)
            ->assertJsonPath('data.is_expired', false);

        $this->assertDatabaseHas('checkout_sessions', [
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::Validated->value,
        ]);
    }

    public function test_start_rejects_empty_cart(): void
    {
        $user = User::factory()->create();

        Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/start')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_start_rejects_missing_retail_price(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000);

        VariantPrice::query()->where('product_variant_id', $variant->id)->delete();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'price_snapshot' => 15000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/start')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_start_rejects_insufficient_inventory(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000, 2);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 15000,
            'price_snapshot' => 15000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/start')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_customer_views_checkout_session(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(12000);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 12000,
            'price_snapshot' => 12000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        $this->getJson("/api/v1/checkout/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.grand_total', '12000.00')
            ->assertJsonCount(1, 'data.cart.items');
    }

    public function test_customer_refreshes_checkout_session_totals(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'price_snapshot' => 10000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        $item->update([
            'quantity' => 3,
            'price_snapshot' => 10000,
        ]);

        $this->postJson("/api/v1/checkout/{$sessionId}/refresh")
            ->assertOk()
            ->assertJsonPath('data.subtotal', '30000.00')
            ->assertJsonPath('data.grand_total', '30000.00')
            ->assertJsonPath('data.status', 'validated');
    }

    public function test_expired_session_cannot_be_viewed(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'price_snapshot' => 10000,
            'currency' => 'TZS',
        ]);

        $session = CheckoutSession::factory()->expired()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/checkout/{$session->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session']);

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'status' => CheckoutSessionStatus::Expired->value,
        ]);
    }

    public function test_customer_cancels_checkout_session(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'price_snapshot' => 10000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        $this->deleteJson("/api/v1/checkout/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('checkout_sessions', ['id' => $sessionId]);
    }

    public function test_user_has_checkout_sessions_relationship(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
        ]);

        $this->assertSame(1, $user->checkoutSessions()->count());
        $this->assertTrue($user->checkoutSessions()->first()->is($cart->checkoutSessions()->first()));
    }

    public function test_guest_rejected(): void
    {
        $this->postJson('/api/v1/checkout/start')->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->postJson('/api/v1/checkout/start')->assertUnauthorized();
    }

    public function test_customer_cannot_access_another_customers_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);

        $cart = Cart::factory()->create([
            'user_id' => $owner->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'price_snapshot' => 10000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($owner);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/checkout/{$sessionId}")->assertNotFound();
    }
}
