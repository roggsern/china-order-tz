<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
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
