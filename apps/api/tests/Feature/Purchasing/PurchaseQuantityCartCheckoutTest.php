<?php

namespace Tests\Feature\Purchasing;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\ChinaCommercialStock;
use App\Models\ConfigurationPriceTier;
use App\Models\DeliveryAddress;
use App\Models\Department;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseQuantityCartCheckoutTest extends TestCase
{
    public function test_schema_columns_are_nullable_and_existing_products_are_unrestricted(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'minimum_order_quantity'));
        $this->assertTrue(Schema::hasColumn('products', 'order_increment'));

        $product = Product::factory()->tzLocal()->create();
        $this->assertNull($product->minimum_order_quantity);
        $this->assertNull($product->order_increment);

        $user = User::factory()->create();
        ['variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_simple_moq_allows_incomplete_cart_and_rejects_checkout_until_minimum(): void
    {
        $user = User::factory()->create();
        $product = $this->simpleChinaProduct(5000, 20, 6, null);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated()->assertJsonPath('data.items.0.quantity', 2);

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            null,
            2,
        );

        $itemId = Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->firstOrFail()->id;
        $this->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 6])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 6);

        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_simple_moq_accepts_quantity_above_minimum_without_increment(): void
    {
        $user = User::factory()->create();
        $product = $this->simpleChinaProduct(5000, 20, 6, null);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 7,
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_configurable_siblings_aggregate_for_moq_and_ignore_other_products(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $red] = CatalogCartFixture::purchasable(10000, 20);
        $blue = $this->addTzVariant($product, 10000, 20);
        $black = $this->addTzVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        ['product' => $other, 'variant' => $otherVariant] = CatalogCartFixture::purchasable(4000, 20);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $red->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $blue->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            null,
            4,
        );

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $otherVariant->id,
            'quantity' => 5,
        ])->assertCreated();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            null,
            4,
        );

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $black->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/start')->assertCreated();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_increment_valid_and_invalid_aggregates(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 30);
        $variantB = $this->addTzVariant($product, 10000, 30);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 4,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 3,
        ])->assertCreated();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            3,
            7,
        );

        $itemB = Cart::query()->where('user_id', $user->id)->firstOrFail()
            ->items()->where('product_variant_id', $variantB->id)->firstOrFail();
        $this->patchJson('/api/v1/cart/items/'.$itemB->id, ['quantity' => 2])->assertOk();

        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_removing_a_sibling_can_leave_an_incomplete_cart(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 20);
        $variantB = $this->addTzVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 4,
        ])->assertCreated();
        $added = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/start')->assertCreated();

        $lineB = $this->line($added->json('data.items'), $variantB->id);
        $this->deleteJson('/api/v1/cart/items/'.$lineB['id'])->assertOk();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            null,
            4,
        );
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->count());
    }

    public function test_order_creation_revalidates_current_rules_after_admin_change(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ])->assertCreated();

        $sessionId = $this->postJson('/api/v1/checkout/start')->assertCreated()->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId, ['shipping_choice' => 'self_pickup']);

        $product->forceFill(['minimum_order_quantity' => 6])->save();

        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'purchase_quantity_unsatisfied');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(4, (int) Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->value('quantity'));
    }

    public function test_volume_tier_does_not_allow_illegal_increment_to_checkout(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 40);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();
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
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ])->assertCreated();
        $this->assertSame('9000.00', $illegal->json('data.items.0.unit_price'));

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            3,
            10,
        );

        $itemId = $illegal->json('data.items.0.id');
        $this->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 12])
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', '9000.00');

        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '108000.00');
    }

    public function test_coupon_cannot_bypass_illegal_purchase_quantity(): void
    {
        config(['promotions.reject_low_margin' => false]);

        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Promotion::query()->create([
            'name' => 'Cannot skip MOQ',
            'code' => 'SKIPMOQ',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'value' => 50,
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ])->assertCreated();

        $start = $this->postJson('/api/v1/checkout/start')->assertUnprocessable();
        $this->assertSame('purchase_quantity_unsatisfied', $start->json('code'));

        $this->postJson('/api/v1/promotions/validate', ['code' => 'SKIPMOQ'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'purchase_quantity_unsatisfied');

        $this->postJson('/api/v1/promotions/apply', ['code' => 'SKIPMOQ'])
            ->assertUnprocessable();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_rejects_when_increment_changes_after_cart_construction(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill(['minimum_order_quantity' => 4])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/start')->assertCreated();

        $product->forceFill(['order_increment' => 2])->save();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            4,
            2,
            5,
        );
        $this->assertSame(5, (int) Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->value('quantity'));
    }

    public function test_increment_without_stored_moq_is_fail_closed_at_checkout(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill(['order_increment' => 3])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            3,
            3,
            1,
        );

        $itemId = Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->firstOrFail()->id;
        $this->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 3])->assertOk();
        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_moq_one_with_increment_three_allows_one_four_seven(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill([
            'minimum_order_quantity' => 1,
            'order_increment' => 3,
        ])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            1,
            3,
            2,
        );

        $itemId = Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->firstOrFail()->id;
        $this->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 4])->assertOk();
        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_shipping_unit_rate_is_unchanged_when_moq_is_satisfied(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::chinaPurchasable(10000, 20);
        $variantB = $this->addChinaVariant($product, 10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        $this->forceAirShipping($product, 3000);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 4,
            'shipping_method' => 'air',
        ])->assertCreated();
        $after = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 2,
            'shipping_method' => 'air',
        ])->assertCreated();

        $lineA = $this->line($after->json('data.items'), $variantA->id);
        $lineB = $this->line($after->json('data.items'), $variantB->id);
        $this->assertSame('3000.00', $lineA['shipping_price']);
        $this->assertSame('3000.00', $lineB['shipping_price']);
        $this->assertSame('air', $lineA['shipping_method']);

        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '60000.00')
            ->assertJsonPath('data.shipping_total', '18000.00');
    }

    public function test_per_sku_inventory_stays_independent_while_siblings_can_meet_moq(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 4);
        $variantB = $this->addTzVariant($product, 10000, 6);
        $product->forceFill(['minimum_order_quantity' => 10])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 5,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 4,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 6,
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/start')->assertCreated();
    }

    public function test_sibling_stock_is_not_pooled_when_one_sku_exceeds_own_stock(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 4);
        $variantB = $this->addTzVariant($product, 10000, 6);
        $product->forceFill(['minimum_order_quantity' => 10])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 5,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 5,
        ])->assertUnprocessable()->assertJsonPath('code', 'business_rule_violated');

        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->count());
        $this->postJson('/api/v1/checkout/start')->assertUnprocessable();
    }

    public function test_simple_product_stock_below_moq_can_sit_in_cart_but_cannot_checkout(): void
    {
        $user = User::factory()->create();
        $product = $this->simpleChinaProduct(5000, 7, 10, null);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 7,
        ])->assertCreated();

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            10,
            null,
            7,
        );
    }

    public function test_china_import_and_tz_local_use_the_same_rule(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        ['variant' => $tzVariant, 'product' => $tzProduct] = CatalogCartFixture::purchasable(8000, 20);
        $tzProduct->forceFill(['minimum_order_quantity' => 4])->save();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $tzVariant->id,
            'quantity' => 4,
        ])->assertCreated();
        $this->postJson('/api/v1/checkout/start')->assertCreated();

        $this->deleteJson('/api/v1/cart')->assertOk();

        ['variant' => $cnVariant, 'product' => $cnProduct] = CatalogCartFixture::chinaPurchasable(8000, 20);
        $cnProduct->forceFill(['minimum_order_quantity' => 4])->save();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $cnVariant->id,
            'quantity' => 3,
        ])->assertCreated();
        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $cnProduct->id,
            4,
            null,
            3,
        );
    }

    public function test_cart_construction_http_routes_stay_soft_for_incomplete_aggregates(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variantA] = CatalogCartFixture::purchasable(10000, 20);
        $variantB = $this->addTzVariant($product, 10000, 20);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantA->id,
            'quantity' => 2,
        ])->assertCreated();

        $added = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variantB->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->assertSame(4, (int) $added->json('data.item_count'));

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 4);

        $lineA = $this->line($added->json('data.items'), $variantA->id);
        $this->patchJson('/api/v1/cart/items/'.$lineA['id'], ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.item_count', 5);

        $lineB = $this->line($added->json('data.items'), $variantB->id);
        $this->deleteJson('/api/v1/cart/items/'.$lineB['id'])->assertOk();

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 3)
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/start'),
            $product->id,
            6,
            3,
            3,
        );
    }

    public function test_checkout_preview_routes_hard_reject_while_get_cart_stays_readable(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        $product->forceFill(['minimum_order_quantity' => 6])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.item_count', 2);

        $this->assertPurchaseQuantityUnsatisfied(
            $this->getJson('/api/v1/checkout'),
            $product->id,
            6,
            null,
            2,
        );
        $this->assertPurchaseQuantityUnsatisfied(
            $this->postJson('/api/v1/checkout/prepare'),
            $product->id,
            6,
            null,
            2,
        );
        $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'self_pickup',
        ])->assertUnprocessable()->assertJsonPath('code', 'purchase_quantity_unsatisfied');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_two_illegal_products_fail_fast_on_first_cart_product(): void
    {
        $user = User::factory()->create();
        ['product' => $first, 'variant' => $firstVariant] = CatalogCartFixture::purchasable(10000, 20);
        ['product' => $second, 'variant' => $secondVariant] = CatalogCartFixture::purchasable(8000, 20);
        $first->forceFill(['minimum_order_quantity' => 6])->save();
        $second->forceFill(['minimum_order_quantity' => 5])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $firstVariant->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $secondVariant->id,
            'quantity' => 2,
        ])->assertCreated();

        $response = $this->postJson('/api/v1/checkout/start');
        $this->assertPurchaseQuantityUnsatisfied($response, $first->id, 6, null, 2);
        $this->assertSame($first->id, $response->json('data.purchase_quantity.product_id'));
        $this->assertNotSame($second->id, $response->json('data.purchase_quantity.product_id'));
        $this->assertIsArray($response->json('data.purchase_quantity'));
        $this->assertArrayNotHasKey(0, $response->json('data.purchase_quantity'));
    }

    public function test_admin_product_update_cannot_mass_assign_purchase_quantity_fields(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->simpleChinaProduct(10000, 20, null, null);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'Renamed without MOQ grant',
            'minimum_order_quantity' => 12,
            'order_increment' => 3,
        ])->assertOk();

        $fresh = $product->fresh();
        $this->assertSame('Renamed without MOQ grant', $fresh?->name);
        $this->assertNull($fresh?->minimum_order_quantity);
        $this->assertNull($fresh?->order_increment);
    }

    public function test_zero_and_fractional_quantities_are_rejected_by_request_validation(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 0,
        ])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1.5,
        ])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    }

    /**
     * @param  TestResponse  $response
     */
    private function assertPurchaseQuantityUnsatisfied(
        $response,
        string $productId,
        ?int $minimum,
        ?int $increment,
        int $eligible,
    ): void {
        $response->assertUnprocessable()
            ->assertJsonPath('code', 'purchase_quantity_unsatisfied')
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.purchase_quantity.product_id', $productId)
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', $minimum)
            ->assertJsonPath('data.purchase_quantity.increment', $increment)
            ->assertJsonPath('data.purchase_quantity.eligible_quantity', $eligible)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', true)
            ->assertJsonPath('errors.purchase_quantity.0', 'This product does not meet the purchase quantity rule.');

        $payload = $response->json('data.purchase_quantity');
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
        $this->assertIsBool($payload['minimum_satisfied']);
        $this->assertIsBool($payload['increment_satisfied']);
        $this->assertIsInt($payload['quantity_to_minimum']);
        $this->assertTrue($payload['blocks_checkout']);
        $this->assertArrayNotHasKey('hasRestriction', $payload);
        $this->assertArrayNotHasKey('isLegal', $payload);
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

    private function simpleChinaProduct(float $price, int $stock, ?int $minimum, ?int $increment): Product
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
        $product = Product::factory()->fromChina()->create([
            'category_id' => $leaf->id,
            'catalog_product_type_id' => $cpt->id,
            'price' => $price,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'air_shipping_price' => 2000,
            'minimum_order_quantity' => $minimum,
            'order_increment' => $increment,
        ]);
        ChinaCommercialStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'available_quantity' => $stock,
                'reserved_quantity' => 0,
                'ordered_quantity' => 0,
            ],
        );

        return $product->fresh(['commerceChannel']) ?? $product;
    }
}
