<?php

namespace Tests\Feature\ProductPurchasability;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\PurchasabilityPath;
use App\Enums\CommerceChannelCode;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Cart\ResolveCartPurchasable;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2A-1 — Product Purchasability Policy (ADR 053).
 */
class ProductPurchasabilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProductPurchasabilityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(ProductPurchasabilityPolicy::class);
    }

    public function test_simple_product_resolves_simple_path(): void
    {
        $product = $this->makeSimpleProduct();

        $this->assertSame(PurchasabilityPath::Simple, $this->policy->resolvePath($product));
        $this->assertFalse($this->policy->hasSellableVariants($product));
        $this->assertCount(0, $product->variants);
    }

    public function test_variant_product_resolves_variant_path(): void
    {
        ['product' => $product] = CatalogCartFixture::purchasable();

        $fresh = $product->fresh(['variants.prices', 'variants.inventories']);

        $this->assertSame(PurchasabilityPath::Variant, $this->policy->resolvePath($fresh));
        $this->assertTrue($this->policy->hasSellableVariants($fresh));

        $result = $this->policy->evaluate($fresh);
        $this->assertSame(PurchasabilityPath::Variant, $result->path);
        $this->assertTrue($result->isPurchasable);
    }

    public function test_incomplete_variants_do_not_force_variant_path(): void
    {
        $product = $this->makeSimpleProduct(['price' => 5000]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => null,
        ]);

        $fresh = $product->fresh(['variants.prices', 'variants.inventories', 'inventory']);

        $this->assertSame(PurchasabilityPath::Simple, $this->policy->resolvePath($fresh));
        $this->assertTrue($this->policy->isPurchasable($fresh));
    }

    public function test_visibility_is_independent_of_purchasability(): void
    {
        $privatePurchasable = $this->makeSimpleProduct([
            'visibility' => ProductVisibility::Private,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        $this->assertTrue($this->policy->isPurchasable($privatePurchasable));
        $this->assertFalse($this->policy->isVisible($privatePurchasable));

        $visibleOutOfStock = $this->makeSimpleProduct([
            'visibility' => ProductVisibility::Public,
            'lifecycle_status' => ProductLifecycleStatus::OutOfStock,
            'is_active' => true,
            'price' => 15000,
        ]);

        $this->assertFalse($this->policy->isPurchasable($visibleOutOfStock));
        $this->assertTrue($this->policy->isVisible($visibleOutOfStock));
    }

    public function test_draft_product_is_not_purchasable(): void
    {
        $product = $this->makeSimpleProduct([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => true,
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertFalse($result->isPurchasable);
        $this->assertContains('Product lifecycle must be active.', $result->errors);
    }

    public function test_inactive_product_is_not_purchasable(): void
    {
        $product = $this->makeSimpleProduct([
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => false,
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertFalse($result->isPurchasable);
        $this->assertContains('Product lifecycle must be active.', $result->errors);
    }

    public function test_product_without_valid_pricing_is_not_purchasable(): void
    {
        $product = $this->makeSimpleProduct(['price' => 0]);

        $result = $this->policy->evaluate($product);

        $this->assertSame(PurchasabilityPath::Simple, $result->path);
        $this->assertFalse($result->isPurchasable);
        $this->assertContains(
            'Simple products require a valid base price greater than zero.',
            $result->errors,
        );
    }

    public function test_product_with_valid_pricing_is_purchasable(): void
    {
        $product = $this->makeSimpleProduct(['price' => 12000]);

        $result = $this->policy->evaluate($product);

        $this->assertSame(PurchasabilityPath::Simple, $result->path);
        $this->assertTrue($result->isPurchasable);
        $this->assertTrue($result->errors === []);
    }

    public function test_cart_resolves_simple_product_without_variant(): void
    {
        $product = $this->makeSimpleProduct(['price' => 18000]);

        Inventory::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->update(['quantity' => 8]);

        $resolved = app(ResolveCartPurchasable::class)->handle(
            $product->id,
            null,
            2,
            'TZS',
        );

        $this->assertSame($product->id, $resolved['product']->id);
        $this->assertNull($resolved['variant']);
        $this->assertSame('18000.00', $resolved['unit_price']);
    }

    public function test_cart_requires_variant_for_variant_products(): void
    {
        ['product' => $product] = CatalogCartFixture::purchasable(22000, 5);

        $this->expectException(ValidationException::class);

        app(ResolveCartPurchasable::class)->handle(
            $product->id,
            null,
            1,
            'TZS',
        );
    }

    public function test_cart_accepts_sellable_variant(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000, 5);

        $resolved = app(ResolveCartPurchasable::class)->handle(
            $product->id,
            $variant->id,
            1,
            'TZS',
        );

        $this->assertSame($variant->id, $resolved['variant']->id);
        $this->assertSame('22000.00', $resolved['unit_price']);
    }

    public function test_china_import_product_with_shipping_option_can_be_published(): void
    {
        $product = $this->makePublishableProduct(CommerceChannelCode::ChinaImport);
        ProductShippingOption::factory()->air(8000)->create(['product_id' => $product->id]);

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
        ]);

        $this->policy->assertPublishable($fresh ?? $product);
        $this->assertTrue(true);
    }

    public function test_china_import_product_without_shipping_is_blocked_from_publish(): void
    {
        $product = $this->makePublishableProduct(CommerceChannelCode::ChinaImport);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
        ]);

        try {
            $this->policy->assertPublishable($fresh ?? $product);
            $this->fail('Expected ValidationException for missing China shipping options.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shipping_options', $exception->errors());
        }
    }

    public function test_tz_local_product_without_shipping_can_be_published(): void
    {
        $product = $this->makePublishableProduct(CommerceChannelCode::TzLocal);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
        ]);

        $this->policy->assertPublishable($fresh ?? $product);
        $this->assertTrue(true);
    }

    public function test_tz_local_product_without_store_is_blocked_from_publish(): void
    {
        $product = $this->makePublishableProduct(CommerceChannelCode::TzLocal, [
            'store_id' => null,
        ]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
        ]);

        try {
            $this->policy->assertPublishable($fresh ?? $product);
            $this->fail('Expected ValidationException for TZ_LOCAL product without store.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('store_id', $exception->errors());
        }
    }

    public function test_tz_local_product_with_store_can_be_published(): void
    {
        $store = $this->makeTzStore();
        $product = $this->makePublishableProduct(CommerceChannelCode::TzLocal, [
            'store_id' => $store->id,
        ]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
        ]);

        $this->policy->assertPublishable($fresh ?? $product);
        $this->assertTrue(true);
    }

    public function test_china_import_product_without_store_can_be_published(): void
    {
        $product = $this->makePublishableProduct(CommerceChannelCode::ChinaImport, [
            'store_id' => null,
        ]);
        ProductShippingOption::factory()->air(8000)->create(['product_id' => $product->id]);

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
        ]);

        $this->policy->assertPublishable($fresh ?? $product);
        $this->assertTrue(true);
    }

    public function test_updating_active_tz_local_product_and_removing_store_fails_validation(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $store = $this->makeTzStore();
        $product = $this->makePublishableProduct(CommerceChannelCode::TzLocal, [
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'store_id' => null,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_updating_draft_tz_local_product_can_remove_store(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $store = $this->makeTzStore();
        $product = $this->makePublishableProduct(CommerceChannelCode::TzLocal, [
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'store_id' => null,
        ])
            ->assertOk();

        $this->assertNull(Product::query()->whereKey($product->id)->value('store_id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishableProduct(
        CommerceChannelCode $channelCode,
        array $overrides = [],
    ): Product {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $channelId = CommerceChannel::query()->where('code', $channelCode->value)->value('id')
            ?? match ($channelCode) {
                CommerceChannelCode::TzLocal => CommerceChannel::factory()->tanzania()->create()->id,
                default => CommerceChannel::factory()->china()->create()->id,
            };

        $defaults = [
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 10000,
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $channelId,
            'fulfillment_source' => $channelCode->fulfillmentSource(),
        ];

        if ($channelCode === CommerceChannelCode::TzLocal && ! array_key_exists('store_id', $overrides)) {
            $defaults['store_id'] = $this->makeTzStore()->id;
        }

        $product = Product::factory()->create(array_merge($defaults, $overrides));

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 10,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory', 'variants']) ?? $product;
    }

    private function makeTzStore(): Store
    {
        return Store::query()->create([
            'code' => 'TZ'.strtoupper(substr((string) str()->uuid(), 0, 4)),
            'name' => 'Test TZ Store',
            'slug' => 'test-tz-store-'.str()->random(8),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSimpleProduct(array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 10000,
        ], $overrides));

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 10,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory', 'variants']) ?? $product;
    }
}
