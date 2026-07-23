<?php

namespace Tests\Feature\Cart;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2A-1 remediation — Simple + Variant HTTP cart / buy-now / checkout (ADR 053).
 */
class ProductPurchasabilityHttpCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_product_http_cart_without_variant_succeeds(): void
    {
        $user = User::factory()->create();
        $product = $this->makePublishableSimpleProduct(18000, 12);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.product_variant_id', null)
            ->assertJsonPath('data.items.0.unit_price', '18000.00')
            ->assertJsonPath('data.items.0.price_snapshot', '18000.00')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 2,
            'unit_price' => 18000,
        ]);
    }

    public function test_simple_product_buy_now_without_variant_reaches_checkout_session(): void
    {
        $user = User::factory()->create();
        $product = $this->makePublishableSimpleProduct(22000, 5);

        Sanctum::actingAs($user);

        $buyNow = $this->postJson('/api/v1/cart/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_type', 'buy_now')
            ->assertJsonPath('data.ready_for_checkout', true);

        $cartId = $buyNow->json('data.cart.id');
        $this->assertNotNull($cartId);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cartId,
            'product_id' => $product->id,
            'product_variant_id' => null,
        ]);

        // Buy-now uses a CheckoutSession cart; move lines into Active cart for checkout/start.
        $active = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);
        CartItem::factory()->create([
            'cart_id' => $active->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'unit_price' => 22000,
            'price_snapshot' => 22000,
            'currency' => 'TZS',
        ]);

        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_simple_product_checkout_e2e_creates_order_with_null_variant_snapshot(): void
    {
        $user = User::factory()->create();
        $product = $this->makePublishableSimpleProduct(15000, 8);
        $product->update(['name' => 'Simple Snapshot Mug']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);

        $response = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Simple Snapshot Mug')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total', '30000.00');

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name_snapshot' => 'Simple Snapshot Mug',
        ]);

        $product->update(['name' => 'Renamed After Order']);
        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Simple Snapshot Mug');

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $sessionId,
            'status' => CheckoutSessionStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'checkout_session_id' => $sessionId,
        ]);
    }

    public function test_variant_product_missing_variant_returns_422(): void
    {
        $user = User::factory()->create();
        ['product' => $product] = CatalogCartFixture::purchasable(20000, 5);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_variant_product_valid_variant_succeeds(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(20000, 5);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.unit_price', '20000.00');
    }

    public function test_variant_belonging_to_another_product_returns_422(): void
    {
        $user = User::factory()->create();
        ['product' => $product] = CatalogCartFixture::purchasable(10000, 5);
        ['variant' => $foreign] = CatalogCartFixture::purchasable(12000, 5);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $foreign->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_inactive_variant_returns_422(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 5);
        $variant->update(['is_active' => false]);

        // Keep another sellable variant so path remains Variant.
        $active = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => null,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $active->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 11000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $active->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_non_sellable_variant_returns_422(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $keep] = CatalogCartFixture::purchasable(10000, 5);

        $bare = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $bare->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_variant_id']);

        $this->assertTrue($keep->is_active);
    }

    public function test_simple_product_rejects_unexpected_variant(): void
    {
        $user = User::factory()->create();
        $product = $this->makePublishableSimpleProduct(9000, 4);
        $foreign = ProductVariant::factory()->create(['is_active' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $foreign->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_non_purchasable_simple_product_returns_422(): void
    {
        $user = User::factory()->create();
        $product = $this->makePublishableSimpleProduct(5000, 3);
        $product->update([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    private function makePublishableSimpleProduct(float $price, int $stock): Product
    {
        $department = Department::factory()->create();
        $leaf = Category::factory()->forDepartment($department)->create([
            'parent_id' => null,
            'is_active' => true,
            'origin' => 'china',
        ]);
        $cpt = CatalogProductType::factory()->create([
            'subcategory_id' => $leaf->id,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $leaf->id,
            'catalog_product_type_id' => $cpt->id,
            'price' => $price,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);

        Inventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => $stock,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 1,
            ],
        );

        return $product->fresh(['inventory', 'catalogProductType']) ?? $product;
    }
}
