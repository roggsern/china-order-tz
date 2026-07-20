<?php

namespace Tests\Feature\Orders;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\User;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_order_from_validated_checkout_session(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);
        $product->update(['name' => 'Snapshot Phone']);
        $variant->update(['name' => '128GB / Black', 'sku' => 'PHONE-128-BLK']);

        $this->seedCart($user, $product->id, $variant->id, 2, 25000);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);

        $response = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.subtotal', '50000.00')
            ->assertJsonPath('data.shipping_total', '0.00')
            ->assertJsonPath('data.tax_total', '0.00')
            ->assertJsonPath('data.discount_total', '0.00')
            ->assertJsonPath('data.grand_total', '50000.00')
            ->assertJsonPath('data.checkout_session_id', $sessionId)
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Snapshot Phone')
            ->assertJsonPath('data.items.0.variant_name_snapshot', '128GB / Black')
            ->assertJsonPath('data.items.0.sku_snapshot', 'PHONE-128-BLK')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total', '50000.00');

        $orderNumber = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('/^COTZ-\d{8}-\d{6}$/', $orderNumber);

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $sessionId,
            'status' => CheckoutSessionStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'checkout_session_id' => $sessionId,
            'status' => OrderStatus::PendingPayment->value,
            'order_number' => $orderNumber,
        ]);

        // Permanent snapshots survive product rename.
        $product->update(['name' => 'Renamed Later']);
        $orderId = $response->json('data.id');
        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Snapshot Phone');
    }

    public function test_rejects_expired_checkout_session(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $cart = $this->seedCart($user, $product->id, $variant->id, 1, 10000);

        $session = CheckoutSession::factory()->expired()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::Expired,
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/from-checkout/{$session->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session']);
    }

    public function test_rejects_invalid_checkout_without_retail_price(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $this->seedCart($user, $product->id, $variant->id, 1, 10000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);

        VariantPrice::query()->where('product_variant_id', $variant->id)->delete();

        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_rejects_draft_checkout_session(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $cart = $this->seedCart($user, $product->id, $variant->id, 1, 10000);

        $session = CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::Draft,
            'subtotal' => 10000,
            'grand_total' => 10000,
            'expires_at' => now()->addMinutes(30),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/from-checkout/{$session->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session']);
    }

    public function test_customer_lists_and_views_order_engine_order(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(18000);
        $this->seedCart($user, $product->id, $variant->id, 1, 18000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")->json('data.id');

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId)
            ->assertJsonPath('data.0.status', 'pending_payment');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.status', 'pending_payment');
    }

    public function test_order_ownership_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $this->seedCart($owner, $product->id, $variant->id, 1, 10000);

        Sanctum::actingAs($owner);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/orders/{$orderId}")->assertNotFound();
        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")->assertNotFound();
    }

    public function test_relationships(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $this->seedCart($user, $product->id, $variant->id, 1, 10000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")->json('data.id');

        $order = Order::query()->with(['user', 'checkoutSession', 'items.variant'])->findOrFail($orderId);

        $this->assertTrue($order->user->is($user));
        $this->assertSame($sessionId, $order->checkoutSession->id);
        $this->assertSame(1, $order->items->count());
        $this->assertSame($variant->id, $order->items->first()->product_variant_id);
        $this->assertTrue($user->orders()->whereKey($orderId)->exists());
    }

    public function test_guest_and_admin_rejected(): void
    {
        $this->postJson('/api/v1/orders/from-checkout/'.fake()->uuid())->assertUnauthorized();

        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $this->seedCart($user, $product->id, $variant->id, 1, 10000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        Sanctum::actingAs(Admin::factory()->create());
        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertUnauthorized();
    }

    private function seedCart(
        User $user,
        string $productId,
        string $variantId,
        int $quantity,
        float $unitPrice,
    ): Cart {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'price_snapshot' => $unitPrice,
            'currency' => 'TZS',
        ]);

        return $cart;
    }
}
