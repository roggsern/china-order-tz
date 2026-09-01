<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\VariantPriceType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ChinaCommercialStock;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutAggregatedVolumePricingTest extends TestCase
{
    public function test_checkout_recalculates_aggregate_pricing_from_stale_cart_snapshots(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);

        $cart = $this->seedTwoLines($user, $product, $variantA, 6, $variantB, 4, 10000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '80000.00')
            ->assertJsonPath('data.shipping_total', '0.00')
            ->assertJsonPath('data.grand_total', '80000.00');

        $this->assertSame('8000.00', (string) $cart->items()->where('product_variant_id', $variantA->id)->value('unit_price'));
        $this->assertSame('8000.00', (string) $cart->items()->where('product_variant_id', $variantB->id)->value('unit_price'));
    }

    public function test_order_snapshot_uses_checkout_resolved_aggregate_price(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);
        $this->seedTwoLines($user, $product, $variantA, 6, $variantB, 4, 10000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->assertCreated()->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId, [
            'shipping_choice' => 'self_pickup',
        ]);

        $order = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '80000.00')
            ->assertJsonPath('data.grand_total', '80000.00');

        $items = $order->json('data.items');
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $unit = $item['unit_price_snapshot'] ?? $item['unit_price'] ?? null;
            $this->assertSame('8000.00', number_format((float) $unit, 2, '.', ''));
        }
    }

    public function test_variant_specific_tier_wins_while_sibling_uses_product_fallback(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variantA->id,
            'min_quantity' => 10,
            'unit_price' => 7000,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 6,
        ])->assertCreated();
        $after = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $items = $after->json('data.items');
        $this->assertSame('7000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('8000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('74000.00', $after->json('data.subtotal'));
    }

    public function test_percent_off_uses_each_variant_base_price_with_aggregate_eligibility(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 20000, 50);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'tier_type' => 'percent_off',
            'unit_price' => 0,
            'discount_percent' => 20,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 6,
        ])->assertCreated();
        $after = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $items = $after->json('data.items');
        $this->assertSame('8000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('16000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('112000.00', $after->json('data.subtotal'));
    }

    public function test_china_import_shipping_stays_per_unit_when_volume_tier_unlocks(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::chinaPurchasable(10000, 50);
        $variantB = $this->addChinaVariant($product, 10000, 50);
        $this->forceAirShipping($product, 3000);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 6,
            'shipping_method' => 'air',
        ])->assertCreated();
        $after = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
            'shipping_method' => 'air',
        ])->assertCreated();

        $items = $after->json('data.items');
        $lineA = $this->line($items, $variantA->id);
        $lineB = $this->line($items, $variantB->id);

        $this->assertSame('8000.00', $lineA['unit_price']);
        $this->assertSame('8000.00', $lineB['unit_price']);
        $this->assertSame('80000.00', $after->json('data.subtotal'));
        $this->assertSame('3000.00', $lineA['shipping_price']);
        $this->assertSame('3000.00', $lineB['shipping_price']);

        $itemA = CartItem::query()->findOrFail($lineA['id']);
        $itemB = CartItem::query()->findOrFail($lineB['id']);
        $this->assertSame('18000.00', $itemA->shippingSubtotal());
        $this->assertSame('12000.00', $itemB->shippingSubtotal());

        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '80000.00')
            ->assertJsonPath('data.shipping_total', '30000.00')
            ->assertJsonPath('data.grand_total', '110000.00');
    }

    public function test_coupon_applies_after_volume_priced_subtotal(): void
    {
        config(['promotions.reject_low_margin' => false]);

        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);
        $this->seedTwoLines($user, $product, $variantA, 6, $variantB, 4, 10000);

        Promotion::query()->create([
            'name' => 'Volume then coupon',
            'code' => 'AFTERMOQ',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'value' => 10,
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '80000.00')
            ->json('data.id');

        $this->postJson('/api/v1/promotions/apply', [
            'code' => 'AFTERMOQ',
            'checkout_session_id' => $sessionId,
        ])->assertOk()
            ->assertJsonPath('data.subtotal', '80000.00')
            ->assertJsonPath('data.discount_total', '8000.00')
            ->assertJsonPath('data.grand_total', '72000.00');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function line(array $items, string $variantId): array
    {
        foreach ($items as $item) {
            if (($item['product_variant_id'] ?? null) === $variantId) {
                return $item;
            }
        }

        $this->fail('Cart line missing for variant '.$variantId);
    }

    private function productTier(Product $product, int $minQuantity, float $unitPrice): void
    {
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => $minQuantity,
            'unit_price' => $unitPrice,
        ]);
    }

    private function addTzVariant(Product $product, float $retail, int $onHand): ProductVariant
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_default' => false,
            'price' => null,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $retail,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        return $variant;
    }

    private function addChinaVariant(Product $product, float $retail, int $available): ProductVariant
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_default' => false,
            'price' => null,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $retail,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_quantity' => $available,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        return $variant;
    }

    private function forceAirShipping(Product $product, float $price): void
    {
        $product->update(['air_shipping_price' => $price]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->air($price)->create(['product_id' => $product->id]);
    }

    private function seedTwoLines(
        User $user,
        Product $product,
        ProductVariant $variantA,
        int $qtyA,
        ProductVariant $variantB,
        int $qtyB,
        float $staleUnitPrice,
    ): Cart {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantA->id,
            'quantity' => $qtyA,
            'unit_price' => $staleUnitPrice,
            'price_snapshot' => $staleUnitPrice,
            'currency' => 'TZS',
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantB->id,
            'quantity' => $qtyB,
            'unit_price' => $staleUnitPrice,
            'price_snapshot' => $staleUnitPrice,
            'currency' => 'TZS',
        ]);

        return $cart->load('items');
    }
}
