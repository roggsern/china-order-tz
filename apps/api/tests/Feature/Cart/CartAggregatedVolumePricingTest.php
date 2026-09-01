<?php

namespace Tests\Feature\Cart;

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

class CartAggregatedVolumePricingTest extends TestCase
{
    public function test_same_product_variant_lines_aggregate_to_unlock_product_tier(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '10000.00');

        $after = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $items = $after->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame('8000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('8000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('80000.00', $after->json('data.subtotal'));

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.subtotal', '80000.00');
    }

    public function test_same_product_below_tier_keeps_retail(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        $variantB = $this->addTzVariant($product, 10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 5,
        ])->assertCreated();
        $after = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $items = $after->json('data.items');
        $this->assertSame('10000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('10000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('90000.00', $after->json('data.subtotal'));
    }

    public function test_quantity_increase_crosses_tier_and_reprices_siblings(): void
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
        $created = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 3,
        ])->assertCreated();

        $items = $created->json('data.items');
        $this->assertSame('10000.00', $this->line($items, $variantA->id)['unit_price']);
        $lineB = $this->line($items, $variantB->id);

        $updated = $this->patchJson('/api/v1/cart/items/'.$lineB['id'], [
            'quantity' => 4,
        ])->assertOk();

        $items = $updated->json('data.items');
        $this->assertSame('8000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('8000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('80000.00', $updated->json('data.subtotal'));
    }

    public function test_quantity_decrease_leaves_tier_and_reprices_siblings(): void
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
        $created = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $lineB = $this->line($created->json('data.items'), $variantB->id);
        $updated = $this->patchJson('/api/v1/cart/items/'.$lineB['id'], [
            'quantity' => 3,
        ])->assertOk();

        $items = $updated->json('data.items');
        $this->assertSame('10000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('10000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('90000.00', $updated->json('data.subtotal'));
    }

    public function test_removing_variant_line_reprices_remaining_sibling(): void
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
        $created = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated();

        $lineB = $this->line($created->json('data.items'), $variantB->id);
        $after = $this->deleteJson('/api/v1/cart/items/'.$lineB['id'])->assertOk();

        $this->assertCount(1, $after->json('data.items'));
        $this->assertSame('10000.00', $after->json('data.items.0.unit_price'));
        $this->assertSame(6, $after->json('data.items.0.quantity'));
        $this->assertSame('60000.00', $after->json('data.subtotal'));
    }

    public function test_different_products_never_aggregate(): void
    {
        $user = User::factory()->create();
        ['product' => $productA, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 50);
        ['variant' => $variantB] = CatalogCartFixture::purchasable(10000, 50);
        $this->productTier($productA, 10, 8000);

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
        $this->assertSame('10000.00', $this->line($items, $variantA->id)['unit_price']);
        $this->assertSame('10000.00', $this->line($items, $variantB->id)['unit_price']);
        $this->assertSame('100000.00', $after->json('data.subtotal'));
    }

    public function test_simple_product_volume_pricing_uses_its_own_line_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->makeSimpleProduct(10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $below = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 9,
        ])->assertCreated();
        $this->assertSame('10000.00', $below->json('data.items.0.unit_price'));

        $merged = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->assertCount(1, $merged->json('data.items'));
        $this->assertSame(10, $merged->json('data.items.0.quantity'));
        $this->assertSame('8000.00', $merged->json('data.items.0.unit_price'));
        $this->assertSame('80000.00', $merged->json('data.subtotal'));
    }

    public function test_client_submitted_unit_price_is_ignored(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 1,
            'price_snapshot' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '10000.00')
            ->assertJsonPath('data.items.0.price_snapshot', '10000.00');
    }

    public function test_public_quote_does_not_inspect_cart_aggregate(): void
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
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 4,
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', '80000.00');

        $this->postJson('/api/v1/products/'.$product->slug.'/quote', [
            'configuration_id' => $variantA->id,
            'quantity' => 6,
        ])->assertOk()
            ->assertJsonPath('data.unit_price', '10000.00');
    }

    public function test_variant_stock_is_not_aggregated(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 3);
        $this->addTzVariant($product, 10000, 20);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_merged_duplicate_variant_reprices_using_merged_quantity(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);
        $this->productTier($product, 10, 8000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '10000.00');

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ])->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 10)
            ->assertJsonPath('data.items.0.unit_price', '8000.00')
            ->assertJsonPath('data.subtotal', '80000.00');
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

    private function makeSimpleProduct(float $price, int $stock): Product
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
                'quantity' => $stock,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory']) ?? $product;
    }
}
