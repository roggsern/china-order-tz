<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreTaxonomyImportMap;
use App\Services\Catalog\MobileAccessoriesTaxonomyCleanupService;
use App\Support\Catalog\MobileAccessoriesTaxonomy;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAccessoriesTaxonomyCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
    }

    public function test_empty_consumer_electronics_power_banks_is_deactivated(): void
    {
        $competing = $this->createConsumerElectronicsPowerBanks();

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertFalse($result['dry_run']);
        $this->assertContains($competing->id, $result['deactivated_category_ids']);
        $this->assertNotNull($competing->fresh());
        $this->assertNotNull($competing->fresh()->deleted_at);
        $this->assertFalse((bool) $competing->fresh()->is_active);
        $this->assertNotNull(
            Category::query()->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)->first(),
        );
    }

    public function test_dry_run_does_not_mutate_competing_node(): void
    {
        $competing = $this->createConsumerElectronicsPowerBanks();

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: true);

        $this->assertTrue($result['dry_run']);
        $this->assertSame([], $result['deactivated_category_ids']);
        $this->assertNull($competing->fresh()->deleted_at);
        $this->assertTrue($competing->fresh()->is_active);
    }

    public function test_products_and_cpts_are_repointed_to_canonical_leaf(): void
    {
        $canonical = Category::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)
            ->firstOrFail();
        $canonicalType = CatalogProductType::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANK_TYPE_SLUG)
            ->firstOrFail();
        $competing = $this->createConsumerElectronicsPowerBanks();
        $competingType = CatalogProductType::factory()->create([
            'subcategory_id' => $competing->id,
            'name' => 'Power Bank',
            'slug' => 'consumer-electronics-power-banks-power-bank',
        ]);
        $product = Product::factory()->chinaImport()->create([
            'category_id' => $competing->id,
            'catalog_product_type_id' => $competingType->id,
            'name' => 'Anker Power Bank',
        ]);

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertContains($product->id, $result['migrated_product_ids']);
        $this->assertSame($canonical->id, $product->fresh()->category_id);
        $this->assertSame($canonicalType->id, $product->fresh()->catalog_product_type_id);
        $this->assertNotNull($competingType->fresh()->deleted_at);
        $this->assertNotNull($competing->fresh()->deleted_at);
    }

    public function test_import_map_source_is_repointed_and_tz_target_is_preserved(): void
    {
        $canonical = Category::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)
            ->firstOrFail();
        $competing = $this->createConsumerElectronicsPowerBanks();
        $store = Store::query()->create([
            'code' => 'ZION',
            'name' => 'ZION MODE',
            'slug' => 'zion-mode',
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
        $tzTarget = Category::factory()->forStore($store)->create([
            'name' => 'Power Banks',
            'slug' => 'zion-mode-consumer-electronics-power-banks',
        ]);
        $map = StoreTaxonomyImportMap::query()->create([
            'store_id' => $store->id,
            'source_category_id' => $competing->id,
            'target_category_id' => $tzTarget->id,
        ]);

        app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertSame($canonical->id, $map->fresh()->source_category_id);
        $this->assertSame($tzTarget->id, $map->fresh()->target_category_id);
        $this->assertNull($tzTarget->fresh()->deleted_at);
    }

    public function test_seeders_do_not_recreate_consumer_electronics_power_banks_after_cleanup(): void
    {
        $competing = $this->createConsumerElectronicsPowerBanks();
        app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);

        $this->assertNull(Category::query()->where('slug', $competing->slug)->first());
        $this->assertNull(Category::query()->where('slug', 'consumer-electronics-power-banks')->first());
        $this->assertNotNull(
            Category::query()->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)->first(),
        );
    }

    public function test_competing_node_with_children_is_skipped(): void
    {
        $competing = $this->createConsumerElectronicsPowerBanks();
        Category::factory()->china()->child($competing)->create([
            'name' => 'Fast Charge Packs',
            'slug' => 'consumer-electronics-power-banks-fast-charge',
        ]);

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertContains($competing->id, $result['skipped_category_ids']);
        $this->assertNull($competing->fresh()->deleted_at);
    }

    public function test_cpt_level_mobile_accessories_power_banks_migrates_four_products(): void
    {
        $canonical = Category::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)
            ->firstOrFail();
        $canonicalType = CatalogProductType::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANK_TYPE_SLUG)
            ->firstOrFail();
        $canonicalUpdatedAt = $canonical->updated_at?->toJSON();
        $canonicalTypeUpdatedAt = $canonicalType->updated_at?->toJSON();

        [$parent, $competingType, $products] = $this->createMobileAccessoriesPowerBanksCpt(4);
        $variant = ProductVariant::factory()->create([
            'product_id' => $products[0]->id,
            'price' => 120000,
            'sku' => 'PB-VAR-KEEP',
        ]);
        $inventory = Inventory::factory()->forVariant($variant)->create([
            'quantity' => 17,
            'reserved_quantity' => 2,
        ]);

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertCount(0, $result['competing']);
        $this->assertCount(1, $result['competing_product_types']);
        $this->assertTrue($result['competing_product_types'][0]['attribute_compatibility']['compatible']);
        $this->assertCount(4, $result['planned_migrations']);
        $this->assertEqualsCanonicalizing(
            collect($products)->pluck('id')->all(),
            $result['migrated_product_ids'],
        );
        $this->assertContains($competingType->id, $result['deactivated_product_type_ids']);
        $this->assertNotContains($parent->id, $result['deactivated_category_ids']);

        foreach ($products as $product) {
            $fresh = $product->fresh();
            $this->assertSame($canonical->id, $fresh->category_id);
            $this->assertSame($canonicalType->id, $fresh->catalog_product_type_id);
            $this->assertSame($product->slug, $fresh->slug);
            $this->assertSame($product->price, $fresh->price);
        }

        $this->assertSame('PB-VAR-KEEP', $variant->fresh()->sku);
        $this->assertSame('120000.00', (string) $variant->fresh()->price);
        $this->assertSame($variant->id, $inventory->fresh()->product_variant_id);
        $this->assertSame(17, $inventory->fresh()->quantity);
        $this->assertSame(2, $inventory->fresh()->reserved_quantity);

        $this->assertNull($parent->fresh()->deleted_at);
        $this->assertTrue($parent->fresh()->is_active);
        $this->assertSame('mobile-accessories', $parent->fresh()->slug);
        $this->assertNull($competingType->fresh()->deleted_at);
        $this->assertFalse((bool) $competingType->fresh()->is_active);

        $this->assertSame($canonical->id, $canonical->fresh()->id);
        $this->assertSame($canonicalType->id, $canonicalType->fresh()->id);
        $this->assertTrue($canonical->fresh()->is_active);
        $this->assertTrue($canonicalType->fresh()->is_active);
        $this->assertSame($canonicalUpdatedAt, $canonical->fresh()->updated_at?->toJSON());
        $this->assertSame($canonicalTypeUpdatedAt, $canonicalType->fresh()->updated_at?->toJSON());
    }

    public function test_cpt_level_dry_run_reports_migrations_and_changes_nothing(): void
    {
        [$parent, $competingType, $products] = $this->createMobileAccessoriesPowerBanksCpt(4);

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: true);

        $this->assertTrue($result['dry_run']);
        $this->assertSame([], $result['migrated_product_ids']);
        $this->assertSame([], $result['deactivated_product_type_ids']);
        $this->assertCount(4, $result['planned_migrations']);
        $this->assertSame($parent->id, $products[0]->fresh()->category_id);
        $this->assertSame($competingType->id, $products[0]->fresh()->catalog_product_type_id);
        $this->assertTrue($competingType->fresh()->is_active);
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_conflicting_required_attributes_skip_cpt_migration(): void
    {
        $canonicalType = CatalogProductType::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANK_TYPE_SLUG)
            ->firstOrFail();
        $required = CatalogAttribute::factory()->create([
            'name' => 'Battery Chemistry',
            'slug' => 'battery-chemistry-required-test',
            'is_required' => true,
        ]);
        $canonicalType->attributes()->attach($required->id, [
            'is_required' => true,
            'sort_order' => 99,
        ]);

        [$parent, $competingType, $products] = $this->createMobileAccessoriesPowerBanksCpt(1);

        $result = app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertContains($competingType->id, $result['skipped_product_type_ids']);
        $this->assertSame([], $result['migrated_product_ids']);
        $this->assertContains(
            'battery-chemistry-required-test',
            $result['competing_product_types'][0]['attribute_compatibility']['missing_required_on_competing'],
        );
        $this->assertFalse($result['competing_product_types'][0]['attribute_compatibility']['compatible']);
        $this->assertSame($parent->id, $products[0]->fresh()->category_id);
        $this->assertSame($competingType->id, $products[0]->fresh()->catalog_product_type_id);
        $this->assertTrue($competingType->fresh()->is_active);
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_mobile_accessories_import_maps_are_preserved_on_cpt_cleanup(): void
    {
        [$parent, $competingType] = $this->createMobileAccessoriesPowerBanksCpt(1);
        $store = Store::query()->create([
            'code' => 'ROVI',
            'name' => 'ROVI',
            'slug' => 'rovi',
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
        $tzTarget = Category::factory()->forStore($store)->create([
            'name' => 'Mobile Accessories',
            'slug' => 'rovi-mobile-accessories',
        ]);
        $map = StoreTaxonomyImportMap::query()->create([
            'store_id' => $store->id,
            'source_category_id' => $parent->id,
            'target_category_id' => $tzTarget->id,
        ]);

        app(MobileAccessoriesTaxonomyCleanupService::class)->cleanup(dryRun: false);

        $this->assertSame($parent->id, $map->fresh()->source_category_id);
        $this->assertSame($tzTarget->id, $map->fresh()->target_category_id);
        $this->assertNull($tzTarget->fresh()->deleted_at);
        $this->assertNull($parent->fresh()->deleted_at);
        $this->assertFalse((bool) $competingType->fresh()->is_active);
    }

    /**
     * @return array{0: Category, 1: CatalogProductType, 2: list<Product>}
     */
    private function createMobileAccessoriesPowerBanksCpt(int $productCount): array
    {
        $department = Department::query()
            ->where('slug', MobileAccessoriesTaxonomy::CONSUMER_ELECTRONICS_DEPARTMENT_SLUG)
            ->firstOrFail();
        $parent = Category::factory()->china()->forDepartment($department)->create([
            'name' => 'Mobile Accessories',
            'slug' => MobileAccessoriesTaxonomy::MOBILE_ACCESSORIES_SLUG,
            'parent_id' => null,
            'is_active' => true,
            'origin' => CatalogOrigin::China,
        ]);
        $competingType = CatalogProductType::factory()->create([
            'subcategory_id' => $parent->id,
            'name' => 'Power Banks',
            'slug' => 'mobile-accessories-power-banks',
            'is_active' => true,
        ]);

        $names = [
            '120,000mAh 120W Super Fast Charge Power Bank',
            '20,000mAh 66W Fast Charging Power Bank',
            '10,000mAh Mini Portable Power Bank – Fast Charging',
            '20,000mAh 100W Super Fast Charging Power Bank',
        ];

        $products = [];
        for ($index = 0; $index < $productCount; $index++) {
            $products[] = Product::factory()->chinaImport()->create([
                'category_id' => $parent->id,
                'catalog_product_type_id' => $competingType->id,
                'name' => $names[$index] ?? 'Power Bank '.$index,
                'price' => 45000 + ($index * 1000),
            ]);
        }

        return [$parent, $competingType, $products];
    }

    private function createConsumerElectronicsPowerBanks(): Category
    {
        $department = Department::query()
            ->where('slug', MobileAccessoriesTaxonomy::CONSUMER_ELECTRONICS_DEPARTMENT_SLUG)
            ->firstOrFail();

        return Category::factory()->china()->forDepartment($department)->create([
            'name' => MobileAccessoriesTaxonomy::POWER_BANKS_NAME,
            'slug' => 'consumer-electronics-power-banks',
            'parent_id' => null,
            'is_active' => true,
            'origin' => CatalogOrigin::China,
        ]);
    }
}
