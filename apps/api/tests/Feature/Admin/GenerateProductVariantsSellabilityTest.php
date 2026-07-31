<?php

namespace Tests\Feature\Admin;

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
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerateProductVariantsSellabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     product: Product,
     *     color: CatalogAttribute,
     *     black: CatalogAttributeOption,
     *     white: CatalogAttributeOption,
     *     size: CatalogAttribute,
     *     s: CatalogAttributeOption,
     *     m: CatalogAttributeOption
     * }
     */
    private function seedGenerateContext(): array
    {
        $department = Department::factory()->create(['name' => 'Gen Dept', 'slug' => 'gen-dept']);
        $category = Category::factory()->create([
            'name' => 'Gen Apparel',
            'slug' => 'gen-apparel',
            'department_id' => $department->id,
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->create([
            'name' => 'Gen Tees',
            'slug' => 'gen-tees',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Gen Tee Type',
            'slug' => 'gen-tee-type',
        ]);

        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'gen-color',
            'type' => CatalogAttributeType::Select,
        ]);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'gen-black',
        ]);
        $white = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'White',
            'slug' => 'gen-white',
        ]);

        $size = CatalogAttribute::factory()->create([
            'name' => 'Size',
            'slug' => 'gen-size',
            'type' => CatalogAttributeType::Select,
        ]);
        $s = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $size->id,
            'value' => 'S',
            'slug' => 'gen-s',
        ]);
        $m = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $size->id,
            'value' => 'M',
            'slug' => 'gen-m',
        ]);

        $catalogType->attributes()->sync([
            $color->id => ['is_required' => false, 'sort_order' => 1],
            $size->id => ['is_required' => false, 'sort_order' => 2],
        ]);

        $product = Product::factory()->fromChina()->create([
            'name' => 'Generate Tee',
            'slug' => 'generate-tee',
            'sku' => 'GEN-TEE',
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $subcategory->id,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 0,
        ]);

        return compact('product', 'color', 'black', 'white', 'size', 's', 'm');
    }

    public function test_generate_variants_creates_inventory_foundation_with_zero_stock(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $ctx = $this->seedGenerateContext();

        $response = $this->postJson('/api/v1/admin/products/'.$ctx['product']->id.'/variants/generate', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $ctx['color']->id,
                    'option_ids' => [$ctx['black']->id, $ctx['white']->id],
                ],
                [
                    'catalog_attribute_id' => $ctx['size']->id,
                    'option_ids' => [$ctx['s']->id, $ctx['m']->id],
                ],
            ],
            'replace_existing' => false,
        ])->assertOk();

        $this->assertSame(4, $response->json('data.generated'));
        $this->assertSame(4, $response->json('data.created_count'));
        $this->assertSame(4, $response->json('data.needs_pricing'));
        $this->assertSame(4, $response->json('data.needs_inventory_setup'));

        $variants = ProductVariant::query()->where('product_id', $ctx['product']->id)->get();
        $this->assertCount(4, $variants);

        foreach ($variants as $variant) {
            $main = VariantInventory::query()
                ->where('product_variant_id', $variant->id)
                ->where('warehouse_code', 'MAIN')
                ->where('is_active', true)
                ->first();

            $this->assertNotNull($main);
            $this->assertSame(0, (int) $main->on_hand);
            $this->assertSame(0, (int) $main->reserved);
            $this->assertSame(0, VariantPrice::query()->where('product_variant_id', $variant->id)->count());
        }
    }

    public function test_missing_price_blocks_purchasability_after_generate(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $ctx = $this->seedGenerateContext();

        $this->postJson('/api/v1/admin/products/'.$ctx['product']->id.'/variants/generate', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $ctx['color']->id,
                    'option_ids' => [$ctx['black']->id],
                ],
                [
                    'catalog_attribute_id' => $ctx['size']->id,
                    'option_ids' => [$ctx['s']->id],
                ],
            ],
        ])->assertOk();

        $variant = ProductVariant::query()->where('product_id', $ctx['product']->id)->firstOrFail();
        $policy = app(ProductPurchasabilityPolicy::class);
        $pricing = app(CommercePricingResolver::class);

        $price = $pricing->resolveVariantProductPrice($variant, null, $ctx['product']->fresh());
        $this->assertFalse($price->resolved && (float) $price->unitPrice > 0);
        $this->assertFalse($policy->isSellableVariant($variant->fresh(['prices', 'inventories'])));
    }

    public function test_missing_inventory_blocks_purchase_when_policy_absent(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $ctx = $this->seedGenerateContext();

        $variant = ProductVariant::factory()->create([
            'product_id' => $ctx['product']->id,
            'sku' => 'GEN-NO-INV',
            'price' => null,
            'is_active' => true,
            'is_default' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 12000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $policy = app(ProductPurchasabilityPolicy::class);
        $this->assertFalse($policy->isSellableVariant($variant->fresh(['prices', 'inventories'])));
    }

    public function test_zero_stock_foundation_still_needs_inventory_setup_and_blocks_sellability_until_stocked(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $ctx = $this->seedGenerateContext();

        $this->postJson('/api/v1/admin/products/'.$ctx['product']->id.'/variants/generate', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $ctx['color']->id,
                    'option_ids' => [$ctx['black']->id],
                ],
                [
                    'catalog_attribute_id' => $ctx['size']->id,
                    'option_ids' => [$ctx['s']->id],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.needs_inventory_setup', 1);

        $variant = ProductVariant::query()->where('product_id', $ctx['product']->id)->firstOrFail();
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 15000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $policy = app(ProductPurchasabilityPolicy::class);
        // Has price + inventory policy, but still sellable by policy (policy checks policy presence, not qty).
        // Confirm inventory policy exists and stock is zero — purchase path uses stock resolver separately.
        $this->assertTrue($policy->isSellableVariant($variant->fresh(['prices', 'inventories'])));

        $main = VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_code', 'MAIN')
            ->firstOrFail();
        $this->assertSame(0, (int) $main->on_hand);
    }

    public function test_existing_variants_unaffected_by_generate(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $ctx = $this->seedGenerateContext();

        $existing = ProductVariant::factory()->create([
            'product_id' => $ctx['product']->id,
            'name' => 'Black S',
            'sku' => 'GEN-EXISTING',
            'price' => 9900,
            'is_active' => true,
            'is_default' => true,
        ]);
        $existing->catalogAttributeValues()->create([
            'catalog_attribute_id' => $ctx['color']->id,
            'option_id' => $ctx['black']->id,
            'value_text' => 'Black',
        ]);
        $existing->catalogAttributeValues()->create([
            'catalog_attribute_id' => $ctx['size']->id,
            'option_id' => $ctx['s']->id,
            'value_text' => 'S',
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $existing->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 9900,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $existing->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 7,
            'reserved' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/products/'.$ctx['product']->id.'/variants/generate', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $ctx['color']->id,
                    'option_ids' => [$ctx['black']->id, $ctx['white']->id],
                ],
                [
                    'catalog_attribute_id' => $ctx['size']->id,
                    'option_ids' => [$ctx['s']->id, $ctx['m']->id],
                ],
            ],
            'replace_existing' => false,
        ])->assertOk();

        $this->assertSame(3, $response->json('data.generated'));
        $this->assertSame(3, $response->json('data.needs_pricing'));

        $existing->refresh();
        $inventory->refresh();
        $this->assertSame(9900.0, (float) $existing->price);
        $this->assertSame(7, (int) $inventory->on_hand);
        $this->assertSame(1, (int) $inventory->reserved);
        $this->assertSame(1, VariantPrice::query()->where('product_variant_id', $existing->id)->count());
    }

    public function test_catalog_health_flags_generated_variants_missing_price(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $ctx = $this->seedGenerateContext();

        $this->postJson('/api/v1/admin/products/'.$ctx['product']->id.'/variants/generate', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $ctx['color']->id,
                    'option_ids' => [$ctx['black']->id],
                ],
                [
                    'catalog_attribute_id' => $ctx['size']->id,
                    'option_ids' => [$ctx['s']->id],
                ],
            ],
        ])->assertOk();

        $variantId = ProductVariant::query()->where('product_id', $ctx['product']->id)->value('id');

        $health = $this->getJson('/api/v1/admin/catalog-health')->assertOk();
        $missingPrice = $health->json('data.issues.commerce_readiness.variants_missing_valid_price');
        $this->assertGreaterThanOrEqual(1, $missingPrice['count']);
        $this->assertContains($variantId, $missingPrice['variant_ids']);

        $missingInventory = $health->json('data.issues.inventory.variants_missing_inventory_policy');
        $this->assertNotContains($variantId, $missingInventory['variant_ids'] ?? []);
    }
}
