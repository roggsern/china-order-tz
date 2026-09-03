<?php

namespace Tests\Feature\Pricing;

use App\Enums\PriceTierType;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\ConfigurationPriceTier;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolumePricingPresentationTest extends TestCase
{
    public function test_simple_product_quote_presents_volume_pricing_contract(): void
    {
        $product = $this->simpleProduct(10000);
        $this->productTier($product, 10, 8000);
        $this->productTier($product, 50, 7000);

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'quantity' => 1,
        ])->assertOk();

        $quote->assertJsonPath('data.unit_price', '10000.00');
        $quote->assertJsonPath('data.volume_pricing.eligible_quantity', 1);
        $quote->assertJsonPath('data.volume_pricing.base_unit_price', '10000.00');
        $quote->assertJsonPath('data.volume_pricing.resolved_unit_price', '10000.00');
        $quote->assertJsonPath('data.volume_pricing.savings_per_unit', '0.00');
        $quote->assertJsonPath('data.volume_pricing.current_tier', null);
        $quote->assertJsonPath('data.volume_pricing.next_tier.min_quantity', 10);
        $quote->assertJsonPath('data.volume_pricing.next_tier.unit_price', '8000.00');
        $quote->assertJsonPath('data.volume_pricing.quantity_to_next_tier', 9);
        $quote->assertJsonPath('data.volume_pricing.tiers.0.min_quantity', 10);
        $quote->assertJsonPath('data.volume_pricing.tiers.1.min_quantity', 50);
        $this->assertArrayNotHasKey('shipping', $quote->json('data.volume_pricing'));
        $this->assertArrayNotHasKey('shipping_price', $quote->json('data.volume_pricing'));
    }

    public function test_public_quote_quantity_selects_current_and_next_tier(): void
    {
        $product = $this->simpleProduct(10000);
        $this->productTier($product, 10, 8000);
        $this->productTier($product, 50, 7000);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 12])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '8000.00')
            ->assertJsonPath('data.volume_pricing.eligible_quantity', 12)
            ->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 10)
            ->assertJsonPath('data.volume_pricing.current_tier.unit_price', '8000.00')
            ->assertJsonPath('data.volume_pricing.next_tier.min_quantity', 50)
            ->assertJsonPath('data.volume_pricing.quantity_to_next_tier', 38)
            ->assertJsonPath('data.volume_pricing.savings_per_unit', '2000.00')
            ->assertJsonPath('data.volume_pricing.savings_total', '24000.00');
    }

    public function test_quote_applies_hundred_plus_volume_tier_at_99_100_and_101(): void
    {
        $product = $this->simpleProduct(25000);
        Inventory::query()
            ->where('product_id', $product->id)
            ->update(['quantity' => 250]);
        $this->productTier($product, 10, 23000);
        $this->productTier($product, 50, 20000);
        $this->productTier($product, 100, 15000);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 99])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '20000.00')
            ->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 50);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 100])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '15000.00')
            ->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 100);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 101])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '15000.00')
            ->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 100);
    }

    public function test_highest_tier_has_no_next_tier(): void
    {
        $product = $this->simpleProduct(10000);
        $this->productTier($product, 10, 8000);
        $this->productTier($product, 50, 7000);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 50])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '7000.00')
            ->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 50)
            ->assertJsonPath('data.volume_pricing.next_tier', null)
            ->assertJsonPath('data.volume_pricing.quantity_to_next_tier', null)
            ->assertJsonPath('data.volume_pricing.savings_per_unit', '3000.00');
    }

    public function test_percent_off_savings_are_server_computed(): void
    {
        $product = $this->simpleProduct(10000);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'tier_type' => PriceTierType::PercentOff,
            'discount_percent' => 10,
            'unit_price' => 0,
        ]);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 10])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '9000.00')
            ->assertJsonPath('data.volume_pricing.current_tier.type', 'percent_off')
            ->assertJsonPath('data.volume_pricing.current_tier.discount_percent', '10.00')
            ->assertJsonPath('data.volume_pricing.current_tier.unit_price', '9000.00')
            ->assertJsonPath('data.volume_pricing.savings_per_unit', '1000.00')
            ->assertJsonPath('data.volume_pricing.savings_total', '10000.00');
    }

    public function test_fixed_unit_savings_are_server_computed(): void
    {
        $product = $this->simpleProduct(10000);
        $this->productTier($product, 10, 8000);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 10])
            ->assertOk()
            ->assertJsonPath('data.volume_pricing.current_tier.type', 'fixed_unit')
            ->assertJsonPath('data.volume_pricing.savings_per_unit', '2000.00')
            ->assertJsonPath('data.volume_pricing.savings_total', '20000.00');
    }

    public function test_configurable_quote_presents_variant_note_flag(): void
    {
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);

        $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $variantA->id,
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('data.volume_pricing.aggregates_variants', true)
            ->assertJsonPath('data.volume_pricing.eligible_quantity', 2)
            ->assertJsonPath('data.volume_pricing.next_tier.min_quantity', 10);
    }

    public function test_cart_reports_aggregate_same_product_quantity(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 6,
        ])->assertCreated();
        $cart = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $cart->assertJsonPath('data.items.0.volume_pricing.eligible_quantity', 10);
        $cart->assertJsonPath('data.items.1.volume_pricing.eligible_quantity', 10);
        $cart->assertJsonPath('data.items.0.volume_pricing.current_tier.min_quantity', 10);
        $cart->assertJsonPath('data.items.0.unit_price', '8000.00');
        $cart->assertJsonPath('data.items.1.unit_price', '8000.00');
        $this->assertSame(10, $cart->json('data.items.0.volume_pricing.eligible_quantity'));
        $this->assertArrayNotHasKey('shipping', $cart->json('data.items.0.volume_pricing'));
    }

    public function test_different_product_ids_never_aggregate(): void
    {
        $user = User::factory()->create();
        ['product' => $productA, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        ['product' => $productB, 'variant' => $variantB] = CatalogCartFixture::purchasable(10000, 50);
        $this->productTier($productA, 10, 8000);
        $this->productTier($productB, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 6,
        ])->assertCreated();
        $cart = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 6,
        ])->assertCreated();

        $lineA = $this->line($cart->json('data.items'), $variantA->id);
        $lineB = $this->line($cart->json('data.items'), $variantB->id);
        $this->assertSame(6, $lineA['volume_pricing']['eligible_quantity']);
        $this->assertSame(6, $lineB['volume_pricing']['eligible_quantity']);
        $this->assertNull($lineA['volume_pricing']['current_tier']);
        $this->assertSame('10000.00', $lineA['unit_price']);
        $this->assertSame('10000.00', $lineB['unit_price']);
    }

    public function test_variant_specific_tier_precedence_is_intact(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000, 50);
        $this->productTier($product, 10, 18000);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => 7000,
        ]);

        $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $variant->id,
            'quantity' => 5,
        ])->assertOk()
            ->assertJsonPath('data.unit_price', '7000.00')
            ->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 5)
            ->assertJsonPath('data.volume_pricing.current_tier.scope', 'configuration')
            ->assertJsonPath('data.volume_pricing.current_tier.unit_price', '7000.00');
    }

    public function test_quote_does_not_inspect_cart_for_volume_quantity(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '8000.00');

        $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('data.unit_price', '10000.00')
            ->assertJsonPath('data.volume_pricing.eligible_quantity', 2)
            ->assertJsonPath('data.volume_pricing.current_tier', null);
    }

    public function test_client_cannot_influence_payable_unit_price(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '10000.00')
            ->assertJsonMissingPath('data.items.0.volume_pricing.shipping');
    }

    public function test_shipping_price_is_not_discounted_by_volume_pricing(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(10000, 50);
        $this->productTier($product, 10, 8000);
        $air = $product->fresh()->shippingPriceForMethod('air');
        $this->assertNotNull($air);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'shipping_method' => 'air',
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '8000.00')
            ->assertJsonPath('data.items.0.shipping_price', number_format((float) $air, 2, '.', ''))
            ->assertJsonPath('data.items.0.volume_pricing.current_tier.min_quantity', 10);

        $this->assertArrayNotHasKey('shipping', $this->getJson('/api/v1/cart')->json('data.items.0.volume_pricing'));
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

    private function simpleProduct(float $price): Product
    {
        $product = Product::factory()->tzLocal()->create([
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
        ]);

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 100,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh() ?? $product;
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
            'is_active' => true,
        ]);

        return $variant;
    }
}
