<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\Admin;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use App\Support\Catalog\TzTaxonomyImportIdentity;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTzTaxonomyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_UPDATE,
                AdminPermissions::CONFIGURATION_VIEW,
                AdminPermissions::CONFIGURATION_MANAGE,
                AdminPermissions::STORES_VIEW,
            ])->create(),
        );
    }

    public function test_import_root_and_leaf_with_parent_auto_provision(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();

        $response = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(2, $response->json('data.categories_created'));

        $tzTops = Category::query()
            ->where('store_id', $store->id)
            ->where('slug', TzTaxonomyImportIdentity::categorySlug($store->slug, $tops->slug))
            ->firstOrFail();
        $tzBlouses = Category::query()
            ->where('store_id', $store->id)
            ->where('slug', TzTaxonomyImportIdentity::categorySlug($store->slug, $blouses->slug))
            ->firstOrFail();

        $this->assertSame(CatalogOrigin::Tz, $tzTops->origin);
        $this->assertSame(CatalogOrigin::Tz, $tzBlouses->origin);
        $this->assertNull($tzTops->department_id);
        $this->assertNull($tzBlouses->department_id);
        $this->assertSame($store->id, $tzTops->store_id);
        $this->assertSame($store->id, $tzBlouses->store_id);
        $this->assertNull($tzTops->parent_id);
        $this->assertSame($tzTops->id, $tzBlouses->parent_id);

        $tops->refresh();
        $this->assertSame(CatalogOrigin::China, $tops->origin);
        $this->assertSame($department->id, $tops->department_id);
        $this->assertNull($tops->store_id);
    }

    public function test_import_multiple_branches_and_product_types_with_shared_attributes(): void
    {
        [$store, $department, $tops, $blouses, $bottoms, $skirts, $blouseType, $color] =
            $this->seedChinaFashionTreeWithTypes();

        $response = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id, $skirts->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ])->assertOk();

        $this->assertGreaterThanOrEqual(4, $response->json('data.categories_created'));
        $this->assertSame(1, $response->json('data.product_types_created'));

        $tzBlouses = Category::query()
            ->where('store_id', $store->id)
            ->where('slug', TzTaxonomyImportIdentity::categorySlug($store->slug, $blouses->slug))
            ->firstOrFail();

        $tzType = CatalogProductType::query()
            ->where('subcategory_id', $tzBlouses->id)
            ->where('name', 'Women\'s Blouse')
            ->firstOrFail();

        $this->assertSame(
            TzTaxonomyImportIdentity::productTypeSlug($tzBlouses->slug, 'Women\'s Blouse'),
            $tzType->slug,
        );
        $this->assertNotSame($blouseType->id, $tzType->id);

        $tzType->load('attributes');
        $this->assertTrue($tzType->attributes->contains('id', $color->id));
        $this->assertSame(1, CatalogAttribute::query()->whereKey($color->id)->count());

        // China CPT untouched
        $blouseType->refresh();
        $this->assertSame($blouses->id, $blouseType->subcategory_id);
    }

    public function test_repeated_import_is_idempotent(): void
    {
        [$store, $department, , $blouses] = $this->seedChinaFashionTreeWithTypes();

        $payload = [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ];

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", $payload)->assertOk();
        $second = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", $payload)->assertOk();

        $this->assertSame(0, $second->json('data.categories_created'));
        $this->assertGreaterThanOrEqual(2, $second->json('data.categories_reused'));
        $this->assertSame(0, $second->json('data.product_types_created'));
        $this->assertSame(1, $second->json('data.product_types_reused'));

        $this->assertSame(
            1,
            Category::query()
                ->where('store_id', $store->id)
                ->where('name', 'Blouses')
                ->count(),
        );
        $this->assertSame(
            1,
            CatalogProductType::query()
                ->where('name', 'Women\'s Blouse')
                ->whereHas('subcategory', fn ($q) => $q->where('store_id', $store->id))
                ->count(),
        );
    }

    public function test_second_store_gets_independent_rows(): void
    {
        [$zion, $department, , $blouses] = $this->seedChinaFashionTreeWithTypes();
        $rovi = $this->makeStore('ROVI BEAUTY', 'ROVI');

        $payload = [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ];

        $this->postJson("/api/v1/admin/stores/{$zion->id}/taxonomy-import", $payload)->assertOk();
        $this->postJson("/api/v1/admin/stores/{$rovi->id}/taxonomy-import", $payload)->assertOk();

        $this->assertSame(
            0,
            Category::query()->where('store_id', $rovi->id)->where('name', 'Blouses')
                ->whereIn('id', Category::query()->where('store_id', $zion->id)->pluck('id'))
                ->count(),
        );

        $zionBlouses = Category::query()->where('store_id', $zion->id)->where('name', 'Blouses')->firstOrFail();
        $roviBlouses = Category::query()->where('store_id', $rovi->id)->where('name', 'Blouses')->firstOrFail();
        $this->assertNotSame($zionBlouses->id, $roviBlouses->id);

        $zionCpt = CatalogProductType::query()->where('subcategory_id', $zionBlouses->id)->firstOrFail();
        $roviCpt = CatalogProductType::query()->where('subcategory_id', $roviBlouses->id)->firstOrFail();
        $this->assertNotSame($zionCpt->id, $roviCpt->id);
    }

    public function test_manual_tz_categories_preserved_and_reused_when_compatible(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();

        $manual = Category::factory()->forStore($store)->create([
            'name' => 'Tops',
            'slug' => 'manual-tops-custom',
            'parent_id' => null,
        ]);

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $manual->refresh();
        $this->assertSame('manual-tops-custom', $manual->slug);
        $this->assertSame($store->id, $manual->store_id);

        // Compatible same-name root is reused — slug preserved, no duplicate Tops.
        $this->assertSame(
            1,
            Category::query()->where('store_id', $store->id)->where('name', 'Tops')->count(),
        );
        $this->assertSame(
            $manual->id,
            Category::query()->where('store_id', $store->id)->where('name', 'Tops')->value('id'),
        );
    }

    public function test_inactive_and_deleted_source_rejected(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();
        $blouses->update(['is_active' => false]);

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
        ])->assertUnprocessable();

        $blouses->update(['is_active' => true]);
        $blouses->delete();

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
        ])->assertUnprocessable();
    }

    public function test_permission_enforcement(): void
    {
        [$store, $department, , $blouses] = $this->seedChinaFashionTree();

        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
            ])->create(),
        );

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
        ])->assertForbidden();

        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
            ])->create(),
        );

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
        ])->assertForbidden();
    }

    public function test_transaction_rolls_back_on_cpt_slug_collision(): void
    {
        [$store, $department, , $blouses, , , $blouseType] = $this->seedChinaFashionTreeWithTypes();

        $foreignLeaf = Category::factory()->china()->forDepartment($department)->create([
            'name' => 'Other Leaf',
            'slug' => 'other-leaf-collision',
        ]);
        $expectedSlug = TzTaxonomyImportIdentity::productTypeSlug(
            TzTaxonomyImportIdentity::categorySlug($store->slug, $blouses->slug),
            $blouseType->name,
        );
        CatalogProductType::factory()->create([
            'subcategory_id' => $foreignLeaf->id,
            'name' => 'Foreign Type',
            'slug' => $expectedSlug,
        ]);

        $before = Category::query()->where('store_id', $store->id)->count();

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ])->assertUnprocessable();

        $this->assertSame($before, Category::query()->where('store_id', $store->id)->count());
    }

    public function test_source_endpoint_lists_china_taxonomy_only(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTreeWithTypes();

        $data = $this->getJson(
            "/api/v1/admin/stores/{$store->id}/taxonomy-import-source?department_id={$department->id}",
        )->assertOk()->json('data');

        $ids = collect($data['categories'])->pluck('id')->all();
        $this->assertContains($tops->id, $ids);
        $this->assertContains($blouses->id, $ids);

        $blouseRow = collect($data['categories'])->firstWhere('id', $blouses->id);
        $this->assertNotEmpty($blouseRow['product_types']);
        $this->assertTrue($blouseRow['product_types'][0]['has_attribute_mappings']);
    }

    public function test_catalog_product_types_can_filter_by_tz_store(): void
    {
        [$store, $department, , $blouses] = $this->seedChinaFashionTreeWithTypes();

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ])->assertOk();

        $rows = $this->getJson(
            '/api/v1/admin/catalog-product-types?origin=tz&store_id='.$store->id.'&per_page=100',
        )->assertOk()->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('tz', $row['origin']);
            $this->assertSame($store->id, $row['store_id']);
        }
    }

    public function test_storefront_only_shows_target_store_imported_categories(): void
    {
        $this->seed(\Database\Seeders\CommerceChannelSeeder::class);
        [$zion, $department, , $blouses] = $this->seedChinaFashionTreeWithTypes();
        $rovi = $this->makeStore('ROVI BEAUTY', 'ROVI');
        $rovi->update([
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
        $zion->update([
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);

        $this->postJson("/api/v1/admin/stores/{$zion->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $tzBlouses = Category::query()
            ->where('store_id', $zion->id)
            ->where('name', 'Blouses')
            ->firstOrFail();
        $tzChannel = \App\Models\CommerceChannel::query()
            ->where('code', \App\Enums\CommerceChannelCode::TzLocal->value)
            ->firstOrFail();

        // Product-aware navigation: empty imported shells are not advertised.
        \App\Models\Product::factory()->create([
            'name' => 'Zion Blouse Visible',
            'slug' => 'zion-blouse-visible-import',
            'store_id' => $zion->id,
            'category_id' => $tzBlouses->id,
            'commerce_channel_id' => $tzChannel->id,
            'fulfillment_source' => \App\Enums\CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => \App\Enums\ProductLifecycleStatus::Active,
            'visibility' => \App\Enums\ProductVisibility::Public,
            'price' => 25000,
        ]);

        $zionCats = $this->getJson('/api/v1/storefront/tz/stores/'.$zion->slug.'/categories')
            ->assertOk()
            ->json('data');
        $rootNames = collect($zionCats)->pluck('name')->all();
        $this->assertContains('Tops', $rootNames);
        $childNames = collect($zionCats)
            ->flatMap(fn ($row) => collect($row['children'] ?? [])->pluck('name'))
            ->all();
        $this->assertContains('Blouses', $childNames);

        $roviCats = $this->getJson('/api/v1/storefront/tz/stores/'.$rovi->slug.'/categories')
            ->assertOk()
            ->json('data');
        $roviNames = collect($roviCats)->pluck('name')->all();
        $this->assertNotContains('Tops', $roviNames);
        $this->assertNotContains('Blouses', $roviNames);
    }

    public function test_source_includes_inactive_parent_and_zero_product_zero_cpt_leaf(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();

        // No products, no CPT — taxonomy must still appear.
        $this->assertSame(0, \App\Models\Product::query()->where('category_id', $blouses->id)->count());
        $this->assertSame(0, CatalogProductType::query()->where('subcategory_id', $blouses->id)->count());
        $this->assertFalse($tops->is_active);

        $data = $this->getJson(
            "/api/v1/admin/stores/{$store->id}/taxonomy-import-source?department_id={$department->id}",
        )->assertOk()->json('data');

        $ids = collect($data['categories'])->pluck('id')->all();
        $this->assertContains($tops->id, $ids);
        $this->assertContains($blouses->id, $ids);

        $topsRow = collect($data['categories'])->firstWhere('id', $tops->id);
        $blousesRow = collect($data['categories'])->firstWhere('id', $blouses->id);

        $this->assertFalse($topsRow['is_active']);
        $this->assertTrue($topsRow['is_structural_parent']);
        $this->assertTrue($topsRow['importable']);
        $this->assertTrue($blousesRow['is_active']);
        $this->assertFalse($blousesRow['has_product_types']);
        $this->assertSame([], $blousesRow['product_types']);
    }

    public function test_source_exposes_cpt_even_when_leaf_has_zero_products(): void
    {
        [$store, $department, $tops, $blouses, , , $blouseType] = $this->seedChinaFashionTreeWithTypes();

        $this->assertSame(0, \App\Models\Product::query()->where('category_id', $blouses->id)->count());

        $data = $this->getJson(
            "/api/v1/admin/stores/{$store->id}/taxonomy-import-source?department_id={$department->id}",
        )->assertOk()->json('data');

        $blousesRow = collect($data['categories'])->firstWhere('id', $blouses->id);
        $this->assertTrue($blousesRow['has_product_types']);
        $this->assertSame($blouseType->id, $blousesRow['product_types'][0]['id']);
        $this->assertContains($tops->id, collect($data['categories'])->pluck('id')->all());
    }

    public function test_import_category_without_source_cpt_succeeds_and_skips_product_types(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();

        $response = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ])->assertOk();

        $this->assertSame(2, $response->json('data.categories_created'));
        $this->assertSame(0, $response->json('data.product_types_created'));
        $this->assertSame(1, $response->json('data.product_types_skipped_no_source'));

        $tzTops = Category::query()
            ->where('store_id', $store->id)
            ->where('name', 'Tops')
            ->firstOrFail();
        $this->assertTrue($tzTops->is_active);
        $this->assertSame(CatalogOrigin::Tz, $tzTops->origin);

        $tops->refresh();
        $this->assertFalse($tops->is_active);
        $this->assertSame(CatalogOrigin::China, $tops->origin);
    }

    public function test_source_hides_disabled_inactive_leaf_without_children(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();
        $disabled = Category::factory()->china()->forDepartment($department)->create([
            'name' => 'Retired Line',
            'slug' => 'retired-line',
            'parent_id' => null,
            'is_active' => false,
        ]);

        $data = $this->getJson(
            "/api/v1/admin/stores/{$store->id}/taxonomy-import-source?department_id={$department->id}",
        )->assertOk()->json('data');

        $ids = collect($data['categories'])->pluck('id')->all();
        $this->assertContains($tops->id, $ids);
        $this->assertContains($blouses->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
    }

    public function test_reuses_existing_manual_root_tops_instead_of_creating_duplicate(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();

        $existingTops = Category::factory()->forStore($store)->create([
            'name' => 'Tops',
            'slug' => 'tops',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(1, $response->json('data.categories_created'));
        $this->assertSame(1, $response->json('data.categories_reused'));
        $this->assertSame(
            1,
            Category::query()->where('store_id', $store->id)->where('name', 'Tops')->count(),
        );
        $this->assertDatabaseHas('categories', [
            'id' => $existingTops->id,
            'slug' => 'tops',
        ]);

        $tzBlouses = Category::query()
            ->where('store_id', $store->id)
            ->where('name', 'Blouses')
            ->firstOrFail();
        $this->assertSame($existingTops->id, $tzBlouses->parent_id);

        // Idempotent second pass
        $second = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();
        $this->assertSame(0, $second->json('data.categories_created'));
        $this->assertSame(2, $second->json('data.categories_reused'));
        $this->assertSame(
            1,
            Category::query()->where('store_id', $store->id)->where('name', 'Tops')->count(),
        );
    }

    public function test_reuses_seeded_store_slug_style_category(): void
    {
        [$store, $department] = $this->seedChinaFashionTree();
        $chinaDresses = Category::factory()->china()->forDepartment($department)->create([
            'name' => 'Dresses',
            'slug' => 'womens-fashion-dresses',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $seeded = Category::factory()->forStore($store)->create([
            'name' => 'Dresses',
            'slug' => 'zion-mode-dresses',
            'parent_id' => null,
        ]);

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$chinaDresses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(
            1,
            Category::query()->where('store_id', $store->id)->where('name', 'Dresses')->count(),
        );
        $this->assertDatabaseHas('categories', [
            'id' => $seeded->id,
            'slug' => 'zion-mode-dresses',
        ]);
        $this->assertDatabaseMissing('categories', [
            'store_id' => $store->id,
            'slug' => TzTaxonomyImportIdentity::categorySlug($store->slug, $chinaDresses->slug),
        ]);
    }

    public function test_same_name_in_other_store_is_not_merged(): void
    {
        [$zion, $department, $tops, $blouses] = $this->seedChinaFashionTree();
        $rovi = $this->makeStore('ROVI BEAUTY', 'ROVI');
        Category::factory()->forStore($rovi)->create([
            'name' => 'Tops',
            'slug' => 'rovi-tops',
            'parent_id' => null,
        ]);

        $this->postJson("/api/v1/admin/stores/{$zion->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(1, Category::query()->where('store_id', $rovi->id)->where('name', 'Tops')->count());
        $this->assertSame(1, Category::query()->where('store_id', $zion->id)->where('name', 'Tops')->count());
        $this->assertNotSame(
            Category::query()->where('store_id', $rovi->id)->where('name', 'Tops')->value('id'),
            Category::query()->where('store_id', $zion->id)->where('name', 'Tops')->value('id'),
        );
    }

    public function test_claimed_target_not_reused_by_different_china_source(): void
    {
        [$store, $womens, $tops, $blouses] = $this->seedChinaFashionTree();
        $mens = Department::factory()->create([
            'name' => "Men's Fashion",
            'slug' => 'mens-fashion',
            'is_active' => true,
        ]);
        $mensTops = Category::factory()->china()->forDepartment($mens)->create([
            'name' => 'Tops',
            'slug' => 'mens-fashion-tops',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $existing = Category::factory()->forStore($store)->create([
            'name' => 'Tops',
            'slug' => 'tops',
            'parent_id' => null,
        ]);

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $womens->id,
            'category_ids' => [$tops->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $mens->id,
            'category_ids' => [$mensTops->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(2, Category::query()->where('store_id', $store->id)->where('name', 'Tops')->count());
        $this->assertDatabaseHas('categories', ['id' => $existing->id, 'slug' => 'tops']);
        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'slug' => TzTaxonomyImportIdentity::categorySlug($store->slug, $mensTops->slug),
        ]);
    }

    public function test_soft_deleted_category_is_not_reused(): void
    {
        [$store, $department, $tops] = $this->seedChinaFashionTree();
        $deleted = Category::factory()->forStore($store)->create([
            'name' => 'Tops',
            'slug' => 'tops',
            'parent_id' => null,
        ]);
        $deleted->delete();

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$tops->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(
            1,
            Category::query()->where('store_id', $store->id)->where('name', 'Tops')->count(),
        );
        $this->assertNotSame($deleted->id, Category::query()->where('store_id', $store->id)->where('name', 'Tops')->value('id'));
    }

    public function test_ambiguous_same_name_roots_are_not_merged(): void
    {
        [$store, $department, $tops] = $this->seedChinaFashionTree();
        Category::factory()->forStore($store)->create(['name' => 'Tops', 'slug' => 'tops-a', 'parent_id' => null]);
        Category::factory()->forStore($store)->create(['name' => 'Tops', 'slug' => 'tops-b', 'parent_id' => null]);

        $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$tops->id],
            'include_product_types' => false,
            'include_attribute_mappings' => false,
        ])->assertOk();

        $this->assertSame(3, Category::query()->where('store_id', $store->id)->where('name', 'Tops')->count());
    }

    public function test_reuse_preserves_existing_products_and_provisions_cpt_on_reused_leaf(): void
    {
        [$store, $department, $tops, $blouses, , , $blouseType, $color] = $this->seedChinaFashionTreeWithTypes();

        $existingTops = Category::factory()->forStore($store)->create([
            'name' => 'Tops',
            'slug' => 'tops',
            'parent_id' => null,
        ]);
        $existingBlouses = Category::factory()->forStore($store)->child($existingTops)->create([
            'name' => 'Blouses',
            'slug' => 'blouses',
        ]);
        $existingCpt = CatalogProductType::factory()->create([
            'subcategory_id' => $existingBlouses->id,
            'name' => "Women's Blouse",
            'slug' => 'blouses-womens-blouse',
            'is_active' => true,
        ]);

        $product = \App\Models\Product::factory()->create([
            'category_id' => $existingBlouses->id,
            'catalog_product_type_id' => $existingCpt->id,
            'store_id' => $store->id,
            'name' => 'Existing Blouse SKU',
        ]);

        $response = $this->postJson("/api/v1/admin/stores/{$store->id}/taxonomy-import", [
            'department_id' => $department->id,
            'category_ids' => [$blouses->id],
            'include_product_types' => true,
            'include_attribute_mappings' => true,
        ])->assertOk();

        $this->assertSame(0, $response->json('data.categories_created'));
        $this->assertSame(2, $response->json('data.categories_reused'));
        $this->assertSame($existingBlouses->id, $product->fresh()->category_id);
        $this->assertSame($existingCpt->id, $product->fresh()->catalog_product_type_id);
        $this->assertSame(
            1,
            CatalogProductType::query()->where('subcategory_id', $existingBlouses->id)->where('name', "Women's Blouse")->count(),
        );

        $existingCpt->load('attributes');
        $this->assertTrue($existingCpt->attributes->contains('id', $color->id));
        $blouseType->refresh();
        $this->assertSame($blouses->id, $blouseType->subcategory_id);
    }

    public function test_source_preview_marks_reuse_existing(): void
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();
        $existingTops = Category::factory()->forStore($store)->create([
            'name' => 'Tops',
            'slug' => 'tops',
            'parent_id' => null,
        ]);

        $data = $this->getJson(
            "/api/v1/admin/stores/{$store->id}/taxonomy-import-source?department_id={$department->id}",
        )->assertOk()->json('data');

        $topsRow = collect($data['categories'])->firstWhere('id', $tops->id);
        $blousesRow = collect($data['categories'])->firstWhere('id', $blouses->id);
        $this->assertSame('reuse', $topsRow['import_preview']['status']);
        $this->assertSame($existingTops->id, $topsRow['import_preview']['target']['id']);
        $this->assertSame('new', $blousesRow['import_preview']['status']);
    }

    /**
     * @return array{0: Store, 1: Department, 2: Category, 3: Category}
     */
    private function seedChinaFashionTree(): array
    {
        $store = $this->makeStore('ZION MODE', 'ZION');
        $department = Department::factory()->create([
            'name' => 'Women\'s Fashion',
            'slug' => 'womens-fashion',
            'is_active' => true,
        ]);
        $tops = Category::factory()->china()->forDepartment($department)->create([
            'name' => 'Tops',
            'slug' => 'womens-fashion-tops',
            'parent_id' => null,
            // Matches Catalog Bible: parents with children stay inactive for storefront.
            'is_active' => false,
            'sort_order' => 1,
        ]);
        $blouses = Category::factory()->china()->forDepartment($department)->child($tops)->create([
            'name' => 'Blouses',
            'slug' => 'womens-fashion-blouses',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$store, $department, $tops, $blouses];
    }

    /**
     * @return array{
     *     0: Store,
     *     1: Department,
     *     2: Category,
     *     3: Category,
     *     4: Category,
     *     5: Category,
     *     6: CatalogProductType,
     *     7: CatalogAttribute
     * }
     */
    private function seedChinaFashionTreeWithTypes(): array
    {
        [$store, $department, $tops, $blouses] = $this->seedChinaFashionTree();

        $bottoms = Category::factory()->china()->forDepartment($department)->create([
            'name' => 'Bottoms',
            'slug' => 'womens-fashion-bottoms',
            'parent_id' => null,
            'is_active' => false,
        ]);
        $skirts = Category::factory()->china()->forDepartment($department)->child($bottoms)->create([
            'name' => 'Skirts',
            'slug' => 'womens-fashion-skirts',
            'is_active' => true,
        ]);

        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'color-import-test',
        ]);

        $blouseType = CatalogProductType::factory()->create([
            'subcategory_id' => $blouses->id,
            'name' => 'Women\'s Blouse',
            'slug' => 'womens-blouse',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $blouseType->attributes()->sync([
            $color->id => ['is_required' => true, 'sort_order' => 1],
        ]);

        return [$store, $department, $tops, $blouses, $bottoms, $skirts, $blouseType, $color];
    }

    private function makeStore(string $name, string $code): Store
    {
        return Store::query()->create([
            'code' => $code,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
    }
}
