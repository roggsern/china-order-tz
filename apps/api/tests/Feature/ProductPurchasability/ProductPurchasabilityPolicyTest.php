<?php

namespace Tests\Feature\ProductPurchasability;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\PurchasabilityPath;
use App\Models\Category;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\ResolveCartPurchasable;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
