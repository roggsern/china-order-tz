<?php

namespace Tests\Feature\Purchasing;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Database\Factories\Support\CatalogCartFixture;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseQuantityBuyNowTest extends TestCase
{
    public function test_buy_now_rejects_quantity_below_moq(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'purchase_quantity_unsatisfied')
            ->assertJsonPath('data.purchase_quantity.product_id', $product->id)
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 2)
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', 6)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', true);

        $this->assertSame(0, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_buy_now_rejects_illegal_increment(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 7,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'purchase_quantity_unsatisfied')
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 7)
            ->assertJsonPath('data.purchase_quantity.next_legal_quantity', 9);
    }

    public function test_buy_now_accepts_legal_quantity(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 9,
        ])
            ->assertCreated()
            ->assertJsonPath('data.checkout_type', 'buy_now')
            ->assertJsonPath('data.ready_for_checkout', true)
            ->assertJsonPath('data.item_count', 9)
            ->assertJsonPath('data.subtotal', '90000.00');
    }

    public function test_buy_now_still_enforces_per_sku_stock_before_purchase_quantity(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 5);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/cart/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 6,
        ])->assertUnprocessable();

        $this->assertNotSame('purchase_quantity_unsatisfied', $response->json('code'));
        $this->assertSame(0, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Order::query()->count());
    }
}
