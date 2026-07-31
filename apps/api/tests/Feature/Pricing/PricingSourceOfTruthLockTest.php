<?php

namespace Tests\Feature\Pricing;

use App\Enums\CatalogAttributeType;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingSourceOfTruthLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{product: Product, variant: ProductVariant, color: CatalogAttribute, black: CatalogAttributeOption}
     */
    private function seedVariantProductWithConflictingPrices(): array
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Pricing Lock Type',
            'slug' => 'pricing-lock-type',
            'is_active' => true,
        ]);

        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'pricing-lock-color',
            'type' => CatalogAttributeType::Select,
        ]);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'pricing-lock-black',
        ]);
        $catalogType->attributes()->sync([
            $color->id => ['is_required' => true, 'sort_order' => 1],
        ]);

        $product = Product::factory()->chinaImport()->create([
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'slug' => 'pricing-lock-phone',
            'name' => 'Pricing Lock Phone',
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => ProductVisibility::Public,
            'price' => 999,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PRICE-LOCK-BLK',
            'name' => 'Black',
            'price' => 111,
            'is_active' => true,
            'is_default' => true,
        ]);
        $variant->catalogAttributeValues()->create([
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
            'value_text' => 'Black',
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 22000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        return compact('product', 'variant', 'color', 'black');
    }

    public function test_variant_prices_override_legacy_variant_price_in_resolver(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->seedVariantProductWithConflictingPrices();

        $result = app(CommercePricingResolver::class)->resolveVariantProductPrice(
            $variant->fresh(['prices']),
            new CommercePricingContext(allowLegacyVariantFallback: true),
            $product,
        );

        $this->assertTrue($result->resolved);
        $this->assertSame('variant_price_retail', $result->source);
        $this->assertSame('22000.00', $result->unitPrice);
    }

    public function test_configuration_picker_displays_resolver_price_not_legacy(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->seedVariantProductWithConflictingPrices();

        $response = $this->getJson("/api/v1/products/{$product->slug}/configuration")->assertOk();
        $row = collect($response->json('data.configurations'))->firstWhere('id', $variant->id);

        $this->assertNotNull($row);
        $this->assertSame('22000.00', (string) $row['price']);
        $this->assertNotSame('111.00', (string) $row['price']);
    }

    public function test_pdp_displays_resolver_price(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->seedVariantProductWithConflictingPrices();

        $detail = $this->getJson("/api/v1/products/{$product->slug}")->assertOk();
        $this->assertSame('22000.00', (string) $detail->json('data.price'));

        $listedVariant = collect($detail->json('data.variants'))->firstWhere('id', $variant->id);
        $this->assertNotNull($listedVariant);
        $this->assertSame('22000.00', (string) $listedVariant['price']);
        $this->assertSame('22000.00', (string) $listedVariant['effective_price']);
    }

    public function test_admin_price_range_matches_resolver(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        ['product' => $product] = $this->seedVariantProductWithConflictingPrices();

        $list = $this->getJson('/api/v1/admin/products?search='.urlencode('Pricing Lock Phone'))
            ->assertOk();

        $row = collect($list->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($row);
        $this->assertSame('22000.00', (string) $row['price_range']['min']);
        $this->assertSame('22000.00', (string) $row['price_range']['max']);
    }

    public function test_bulk_pricing_updates_variant_prices_and_storefront_display(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        ['product' => $product, 'variant' => $variant] = $this->seedVariantProductWithConflictingPrices();

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'pricing_fixed',
            'product_ids' => [$product->id],
            'payload' => ['amount' => 33000],
        ])->assertOk();

        $this->assertDatabaseHas('variant_prices', [
            'product_variant_id' => $variant->id,
            'amount' => '33000.00',
            'is_active' => true,
        ]);

        // Legacy column remains untouched.
        $this->assertSame(111.0, (float) $variant->fresh()->price);

        $resolver = app(CommercePricingResolver::class)->resolveVariantProductPrice(
            $variant->fresh(['prices']),
            new CommercePricingContext(allowLegacyVariantFallback: true),
            $product->fresh(),
        );
        $this->assertSame('33000.00', $resolver->unitPrice);

        $this->getJson("/api/v1/products/{$product->slug}/configuration")
            ->assertOk()
            ->assertJsonPath('data.configurations.0.price', '33000.00');

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.price', '33000.00');
    }
}
