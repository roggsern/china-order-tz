<?php

namespace Tests\Feature\Purchasing;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Cart;
use App\Models\ConfigurationPriceTier;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseQuantityPresentationTest extends TestCase
{
    public function test_quote_without_rule_returns_null_purchase_quantity(): void
    {
        $product = $this->simpleTzProduct(10000);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '10000.00')
            ->assertJsonPath('data.purchase_quantity', null)
            ->assertJsonPath('data.volume_pricing', null);
    }

    public function test_quote_below_moq_succeeds_with_blocker_metadata(): void
    {
        $product = $this->simpleTzProduct(10000, 6);

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 2])
            ->assertOk();

        $quote->assertJsonPath('data.unit_price', '10000.00')
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', 6)
            ->assertJsonPath('data.purchase_quantity.increment', null)
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 2)
            ->assertJsonPath('data.purchase_quantity.aggregates_variants', false)
            ->assertJsonPath('data.purchase_quantity.minimum_satisfied', false)
            ->assertJsonPath('data.purchase_quantity.increment_satisfied', true)
            ->assertJsonPath('data.purchase_quantity.quantity_to_minimum', 4)
            ->assertJsonPath('data.purchase_quantity.next_legal_quantity', 6)
            ->assertJsonPath('data.purchase_quantity.construction_complete', false)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', true);

        $payload = $quote->json('data.purchase_quantity');
        $this->assertSame([
            'minimum_quantity',
            'increment',
            'eligible_quantity',
            'aggregates_variants',
            'minimum_satisfied',
            'increment_satisfied',
            'quantity_to_minimum',
            'next_legal_quantity',
            'construction_complete',
            'blocks_checkout',
        ], array_keys($payload));
        $this->assertIsInt($payload['minimum_quantity']);
        $this->assertIsInt($payload['eligible_quantity']);
        $this->assertIsInt($payload['quantity_to_minimum']);
        $this->assertIsInt($payload['next_legal_quantity']);
        $this->assertIsBool($payload['aggregates_variants']);
        $this->assertIsBool($payload['blocks_checkout']);
        $this->assertNull($payload['increment']);
    }

    public function test_quote_exact_moq_and_legal_increment(): void
    {
        $product = $this->simpleTzProduct(10000, 6, 3);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 6])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 6)
            ->assertJsonPath('data.purchase_quantity.construction_complete', true)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', false);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 9])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 9)
            ->assertJsonPath('data.purchase_quantity.increment_satisfied', true)
            ->assertJsonPath('data.purchase_quantity.construction_complete', true)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', false);
    }

    public function test_quote_illegal_increment_reports_next_legal_quantity(): void
    {
        $product = $this->simpleTzProduct(10000, 6, 3);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 7])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '10000.00')
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 7)
            ->assertJsonPath('data.purchase_quantity.minimum_satisfied', true)
            ->assertJsonPath('data.purchase_quantity.increment_satisfied', false)
            ->assertJsonPath('data.purchase_quantity.next_legal_quantity', 9)
            ->assertJsonPath('data.purchase_quantity.construction_complete', false)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', true);
    }

    public function test_quote_does_not_inspect_cart_sibling_quantities(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $red] = CatalogCartFixture::purchasable(10000, 20);
        $blue = $this->addTzVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 6,
            'unit_price' => 8000,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $red->id,
            'quantity' => 4,
        ])->assertCreated();

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $blue->id,
            'quantity' => 2,
        ])->assertOk();

        $quote->assertJsonPath('data.purchase_quantity.eligible_quantity', 2);
        $quote->assertJsonPath('data.purchase_quantity.quantity_to_minimum', 4);
        $quote->assertJsonPath('data.purchase_quantity.blocks_checkout', true);
        $quote->assertJsonPath('data.volume_pricing.eligible_quantity', 2);
        $quote->assertJsonPath('data.volume_pricing.current_tier', null);
    }

    public function test_quote_volume_tier_and_illegal_increment_coexist(): void
    {
        $product = $this->simpleTzProduct(10000, 6, 3);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'tier_type' => 'percent_off',
            'discount_percent' => 10,
            'unit_price' => 0,
        ]);

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 10])
            ->assertOk();

        $quote->assertJsonPath('data.unit_price', '9000.00');
        $quote->assertJsonPath('data.volume_pricing.eligible_quantity', 10);
        $quote->assertJsonPath('data.volume_pricing.current_tier.min_quantity', 10);
        $quote->assertJsonPath('data.purchase_quantity.eligible_quantity', 10);
        $quote->assertJsonPath('data.purchase_quantity.increment_satisfied', false);
        $quote->assertJsonPath('data.purchase_quantity.blocks_checkout', true);
        $quote->assertJsonPath('data.purchase_quantity.next_legal_quantity', 12);
        $this->assertArrayNotHasKey('shipping', $quote->json('data.purchase_quantity'));
    }

    public function test_cart_simple_product_below_and_at_moq(): void
    {
        $user = User::factory()->create();
        $product = $this->simpleTzProduct(8000, 6);
        Sanctum::actingAs($user);

        $below = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 4,
        ])->assertCreated();

        $this->assertSame(200, $this->getJson('/api/v1/cart')->status());
        $below->assertJsonPath('data.items.0.purchase_quantity.eligible_quantity', 4);
        $below->assertJsonPath('data.items.0.purchase_quantity.aggregates_variants', false);
        $below->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);
        $below->assertJsonPath('data.items.0.unit_price', '8000.00');
        $this->assertCount(1, $below->json('data.purchase_quantity_blockers'));
        $this->assertSame($product->id, $below->json('data.purchase_quantity_blockers.0.product_id'));

        $itemId = $below->json('data.items.0.id');
        $legal = $this->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 6])->assertOk();
        $legal->assertJsonPath('data.items.0.purchase_quantity.eligible_quantity', 6);
        $legal->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', false);
        $legal->assertJsonPath('data.items.0.unit_price', '8000.00');
        $this->assertSame([], $legal->json('data.purchase_quantity_blockers'));
    }

    public function test_configurable_siblings_share_aggregate_and_isolate_other_products(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $red] = CatalogCartFixture::purchasable(10000, 20);
        $blue = $this->addTzVariant($product, 10000, 20);
        $black = $this->addTzVariant($product, 10000, 20);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();
        ['product' => $other, 'variant' => $otherVariant] = CatalogCartFixture::purchasable(4000, 20);
        $other->forceFill(['minimum_order_quantity' => 5])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $red->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $blue->id,
            'quantity' => 2,
        ])->assertCreated();
        $afterBlack = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $black->id,
            'quantity' => 1,
        ])->assertCreated();
        $withOther = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $otherVariant->id,
            'quantity' => 2,
        ])->assertCreated();

        $items = $withOther->json('data.items');
        $redLine = $this->line($items, $red->id);
        $blueLine = $this->line($items, $blue->id);
        $blackLine = $this->line($items, $black->id);
        $otherLine = $this->line($items, $otherVariant->id);

        $this->assertSame(5, $redLine['purchase_quantity']['eligible_quantity']);
        $this->assertSame(5, $blueLine['purchase_quantity']['eligible_quantity']);
        $this->assertSame(5, $blackLine['purchase_quantity']['eligible_quantity']);
        $this->assertTrue($redLine['purchase_quantity']['aggregates_variants']);
        $this->assertSame(2, $otherLine['purchase_quantity']['eligible_quantity']);
        $this->assertFalse($otherLine['purchase_quantity']['aggregates_variants']);

        $blockers = $withOther->json('data.purchase_quantity_blockers');
        $this->assertCount(2, $blockers);
        $this->assertSame($product->id, $blockers[0]['product_id']);
        $this->assertSame(5, $blockers[0]['eligible_quantity']);
        $this->assertSame($other->id, $blockers[1]['product_id']);
        $this->assertSame(2, $blockers[1]['eligible_quantity']);

        $this->assertSame(5, $afterBlack->json('data.items.0.purchase_quantity.eligible_quantity'));
        $this->getJson('/api/v1/cart')->assertOk();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_cart_mutations_stay_soft_and_update_aggregate_metadata(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 20);
        $variantB = $this->addTzVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 2,
        ])->assertCreated();
        $first->assertJsonPath('data.items.0.purchase_quantity.eligible_quantity', 2);
        $first->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);

        $second = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->assertSame(4, $this->line($second->json('data.items'), $variantA->id)['purchase_quantity']['eligible_quantity']);
        $this->assertSame(4, $this->line($second->json('data.items'), $variantB->id)['purchase_quantity']['eligible_quantity']);

        $lineB = $this->line($second->json('data.items'), $variantB->id);
        $legal = $this->patchJson('/api/v1/cart/items/'.$lineB['id'], ['quantity' => 4])->assertOk();
        $this->assertSame(6, $this->line($legal->json('data.items'), $variantA->id)['purchase_quantity']['eligible_quantity']);
        $this->assertFalse($this->line($legal->json('data.items'), $variantA->id)['purchase_quantity']['blocks_checkout']);
        $this->assertSame([], $legal->json('data.purchase_quantity_blockers'));

        $removed = $this->deleteJson('/api/v1/cart/items/'.$lineB['id'])->assertOk();
        $removed->assertJsonPath('data.items.0.purchase_quantity.eligible_quantity', 2);
        $removed->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);
        $this->assertCount(1, $removed->json('data.purchase_quantity_blockers'));
        $this->getJson('/api/v1/cart')->assertOk();
    }

    public function test_get_cart_reflects_current_product_rule_without_rewrite(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ])->assertCreated()->assertJsonPath('data.items.0.purchase_quantity', null);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.purchase_quantity', null)
            ->assertJsonPath('data.purchase_quantity_blockers', []);

        $product->forceFill(['minimum_order_quantity' => 6])->save();

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.purchase_quantity.minimum_quantity', 6)
            ->assertJsonPath('data.items.0.purchase_quantity.eligible_quantity', 4)
            ->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true)
            ->assertJsonPath('data.purchase_quantity_blockers.0.product_id', $product->id);

        $this->assertSame(4, (int) Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->value('quantity'));
    }

    public function test_malformed_increment_without_moq_matches_runtime_on_quote_and_cart(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill(['order_increment' => 3])->save();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', 3)
            ->assertJsonPath('data.purchase_quantity.increment', 3)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', true);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.purchase_quantity.minimum_quantity', 3)
            ->assertJsonPath('data.purchase_quantity_blockers.0.minimum_quantity', 3);
    }

    public function test_china_import_and_tz_local_share_the_same_presentation_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        ['product' => $tzProduct, 'variant' => $tzVariant] = CatalogCartFixture::purchasable(8000, 20);
        $tzProduct->forceFill(['minimum_order_quantity' => 4])->save();
        $this->postJson("/api/v1/products/{$tzProduct->slug}/quote", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', 4)
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 2);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $tzVariant->id,
            'quantity' => 2,
        ])->assertCreated()->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);

        $this->deleteJson('/api/v1/cart')->assertOk();

        ['product' => $cnProduct, 'variant' => $cnVariant] = CatalogCartFixture::chinaPurchasable(8000, 20);
        $cnProduct->forceFill(['minimum_order_quantity' => 4])->save();
        $this->postJson("/api/v1/products/{$cnProduct->slug}/quote", [
            'configuration_id' => $cnVariant->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', 4)
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', 2);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $cnVariant->id,
            'quantity' => 2,
        ])->assertCreated()->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);
    }

    public function test_purchase_quantity_does_not_change_price_or_shipping(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        $this->forceAirShipping($product, 3000);
        Sanctum::actingAs($user);

        $cart = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'shipping_method' => 'air',
        ])->assertCreated();

        $cart->assertJsonPath('data.items.0.unit_price', '10000.00');
        $cart->assertJsonPath('data.items.0.shipping_price', '3000.00');
        $cart->assertJsonPath('data.items.0.shipping_method', 'air');
        $cart->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);
        $this->assertArrayNotHasKey('shipping', $cart->json('data.items.0.purchase_quantity'));
        $this->assertArrayNotHasKey('shipping_price', $cart->json('data.items.0.purchase_quantity'));
        $this->assertArrayNotHasKey('unit_price', $cart->json('data.items.0.purchase_quantity'));
    }

    public function test_configurable_lines_two_three_one_share_eligible_quantity_six(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $a] = CatalogCartFixture::purchasable(10000, 20);
        $b = $this->addTzVariant($product, 10000, 20);
        $c = $this->addTzVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 8])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $a->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $b->id,
            'quantity' => 3,
        ])->assertCreated();
        $cart = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $c->id,
            'quantity' => 1,
        ])->assertCreated();

        foreach ([$a->id, $b->id, $c->id] as $variantId) {
            $this->assertSame(6, $this->line($cart->json('data.items'), $variantId)['purchase_quantity']['eligible_quantity']);
        }
        $this->assertCount(1, $cart->json('data.purchase_quantity_blockers'));
        $this->assertSame(6, $cart->json('data.purchase_quantity_blockers.0.eligible_quantity'));
    }

    public function test_inactive_variants_do_not_mark_aggregates_variants_true(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $active] = CatalogCartFixture::purchasable(10000, 20);
        $inactive = $this->addTzVariant($product, 10000, 20);
        $inactive->forceFill(['is_active' => false])->save();
        $trashed = $this->addTzVariant($product, 10000, 20);
        $trashed->delete();
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $active->id,
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.purchase_quantity.aggregates_variants', false);
    }

    public function test_cart_get_does_not_issue_per_line_variant_count_queries(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $a] = CatalogCartFixture::purchasable(10000, 20);
        $b = $this->addTzVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $a->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $b->id,
            'quantity' => 2,
        ])->assertCreated();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/cart')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $countQueries = $queries->filter(
            fn (string $sql): bool => str_contains(strtolower($sql), 'product_variants')
                && str_contains(strtolower($sql), 'count('),
        );
        $this->assertTrue($countQueries->isEmpty(), $countQueries->implode("\n"));
    }

    public function test_cart_volume_tier_and_purchase_quantity_coexist_at_ten_and_twelve(): void
    {
        $user = User::factory()->create();
        $product = $this->simpleTzProduct(10000, 6, 3);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'tier_type' => 'percent_off',
            'discount_percent' => 10,
            'unit_price' => 0,
        ]);
        Sanctum::actingAs($user);

        $illegal = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 10,
        ])->assertCreated();
        $illegal->assertJsonPath('data.items.0.unit_price', '9000.00');
        $illegal->assertJsonPath('data.items.0.volume_pricing.current_tier.min_quantity', 10);
        $illegal->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', true);
        $illegal->assertJsonPath('data.items.0.purchase_quantity.increment_satisfied', false);

        $itemId = $illegal->json('data.items.0.id');
        $legal = $this->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 12])->assertOk();
        $legal->assertJsonPath('data.items.0.unit_price', '9000.00');
        $legal->assertJsonPath('data.items.0.volume_pricing.current_tier.min_quantity', 10);
        $legal->assertJsonPath('data.items.0.purchase_quantity.blocks_checkout', false);
        $legal->assertJsonPath('data.purchase_quantity_blockers', []);
    }

    public function test_checkout_422_contract_stays_compatible_and_does_not_gain_presentation_aliases(): void
    {
        $user = User::factory()->create();
        $product = $this->simpleTzProduct(10000, 6, 3);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 4,
        ])->assertCreated();

        $response = $this->postJson('/api/v1/checkout/start')->assertUnprocessable();
        $payload = $response->json('data.purchase_quantity');
        $this->assertSame('purchase_quantity_unsatisfied', $response->json('code'));
        $this->assertSame([
            'product_id',
            'minimum_quantity',
            'increment',
            'eligible_quantity',
            'minimum_satisfied',
            'increment_satisfied',
            'quantity_to_minimum',
            'next_legal_quantity',
            'blocks_checkout',
        ], array_keys($payload));
        $this->assertArrayNotHasKey('aggregates_variants', $payload);
        $this->assertArrayNotHasKey('construction_complete', $payload);
        $this->assertSame(0, Order::query()->count());
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

    private function simpleTzProduct(float $price, ?int $minimum = null, ?int $increment = null): Product
    {
        $product = Product::factory()->tzLocal()->create([
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'minimum_order_quantity' => $minimum,
            'order_increment' => $increment,
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

    private function forceAirShipping(Product $product, float $price): void
    {
        $product->update(['air_shipping_price' => $price]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->air($price)->create(['product_id' => $product->id]);
    }
}
