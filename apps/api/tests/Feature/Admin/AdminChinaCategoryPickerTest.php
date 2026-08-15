<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Department;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Database\Seeders\TzStoreCategorySeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards admin CHINA_IMPORT category picker data without changing storefront taxonomy.
 */
class AdminChinaCategoryPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_china_department_categories_include_inactive_parent_and_active_children(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $department = Department::query()->where('slug', 'mens-fashion')->firstOrFail();
        $clothing = Category::query()
            ->where('slug', 'mens-fashion-clothing')
            ->firstOrFail();
        $tShirts = Category::query()
            ->where('slug', 'mens-fashion-clothing-t-shirts')
            ->firstOrFail();

        $this->assertFalse($clothing->is_active);
        $this->assertTrue($tShirts->is_active);
        $this->assertSame($department->id, $clothing->department_id);
        $this->assertSame($clothing->id, $tShirts->parent_id);

        $response = $this->getJson('/api/v1/admin/categories?origin=china&department_id='.$department->id.'&per_page=100')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($clothing->id, $ids);
        $this->assertContains($tShirts->id, $ids);

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($rows[$clothing->id]['has_active_children']);
        $this->assertFalse($rows[$clothing->id]['selectable']);
        $this->assertFalse($rows[$tShirts->id]['has_active_children']);
        $this->assertTrue($rows[$tShirts->id]['selectable']);
    }

    public function test_networking_power_structural_parent_not_selectable_and_leaves_are(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $department = Department::query()->where('slug', 'computers-office')->firstOrFail();
        $parent = Category::query()->where('slug', 'computers-office-networking-power')->firstOrFail();
        $dcUps = Category::query()
            ->where('slug', 'computers-office-networking-power-dc-ups-router-backup')
            ->firstOrFail();

        $response = $this->getJson(
            '/api/v1/admin/categories?origin=china&department_id='.$department->id.'&per_page=100',
        )
            ->assertOk()
            ->json('data');

        $rows = collect($response)->keyBy('slug');

        $this->assertArrayHasKey('computers-office-networking-power', $rows->all());
        $this->assertFalse($rows['computers-office-networking-power']['is_active']);
        $this->assertTrue($rows['computers-office-networking-power']['has_active_children']);
        $this->assertFalse($rows['computers-office-networking-power']['selectable']);

        $this->assertTrue($rows['computers-office-networking-power-dc-ups-router-backup']['is_active']);
        $this->assertFalse($rows['computers-office-networking-power-dc-ups-router-backup']['has_active_children']);
        $this->assertTrue($rows['computers-office-networking-power-dc-ups-router-backup']['selectable']);
        $this->assertSame($parent->id, $rows['computers-office-networking-power-dc-ups-router-backup']['parent_id']);
        $this->assertSame($dcUps->id, $rows['computers-office-networking-power-dc-ups-router-backup']['id']);
    }

    public function test_deep_hierarchy_fixture_marks_only_deep_leaf_selectable(): void
    {
        $department = Department::factory()->create([
            'slug' => 'deep-dept',
            'name' => 'Deep Dept',
            'is_active' => true,
        ]);

        $root = Category::factory()->create([
            'department_id' => $department->id,
            'parent_id' => null,
            'origin' => CatalogOrigin::China,
            'store_id' => null,
            'name' => 'Root Structural',
            'slug' => 'deep-dept-root',
            'is_active' => false,
        ]);
        $mid = Category::factory()->create([
            'department_id' => $department->id,
            'parent_id' => $root->id,
            'origin' => CatalogOrigin::China,
            'store_id' => null,
            'name' => 'Mid Structural',
            'slug' => 'deep-dept-mid',
            'is_active' => false,
        ]);
        $leaf = Category::factory()->create([
            'department_id' => $department->id,
            'parent_id' => $mid->id,
            'origin' => CatalogOrigin::China,
            'store_id' => null,
            'name' => 'Deep Leaf',
            'slug' => 'deep-dept-leaf',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/v1/admin/categories?origin=china&department_id='.$department->id.'&per_page=100',
        )
            ->assertOk()
            ->json('data');

        $rows = collect($response)->keyBy('id');

        $this->assertFalse($rows[$root->id]['has_active_children']);
        $this->assertFalse($rows[$root->id]['selectable']);
        $this->assertTrue($rows[$mid->id]['has_active_children']);
        $this->assertFalse($rows[$mid->id]['selectable']);
        $this->assertFalse($rows[$leaf->id]['has_active_children']);
        $this->assertTrue($rows[$leaf->id]['selectable']);
    }

    public function test_bible_chrome_root_excluded_from_department_picker(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);

        $department = Department::query()->where('slug', 'computers-office')->firstOrFail();
        $bibleElectronics = Category::query()->where('slug', 'electronics')->firstOrFail();

        $this->assertNull($bibleElectronics->department_id);

        $response = $this->getJson(
            '/api/v1/admin/categories?origin=china&department_id='.$department->id.'&per_page=100',
        )
            ->assertOk()
            ->json('data');

        $ids = collect($response)->pluck('id')->all();
        $this->assertNotContains($bibleElectronics->id, $ids);
        $this->assertTrue(
            collect($response)->every(fn (array $row) => ($row['department_id'] ?? null) === $department->id),
        );
    }

    public function test_soft_deleted_category_absent_from_picker_payload(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $department = Department::query()->where('slug', 'phones-tablets')->firstOrFail();
        $chargers = Category::query()
            ->where('slug', 'phones-tablets-phone-accessories-chargers')
            ->firstOrFail();
        $chargers->delete();

        $response = $this->getJson(
            '/api/v1/admin/categories?origin=china&department_id='.$department->id.'&per_page=100',
        )
            ->assertOk()
            ->json('data');

        $slugs = collect($response)->pluck('slug')->all();
        $this->assertNotContains('phones-tablets-phone-accessories-chargers', $slugs);
        $this->assertNotContains('phones-tablets-chargers', $slugs);
    }

    public function test_china_department_is_active_filter_still_excludes_inactive_parents(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $department = Department::query()->where('slug', 'mens-fashion')->firstOrFail();
        $clothing = Category::query()
            ->where('slug', 'mens-fashion-clothing')
            ->firstOrFail();
        $tShirts = Category::query()
            ->where('slug', 'mens-fashion-clothing-t-shirts')
            ->firstOrFail();

        $response = $this->getJson('/api/v1/admin/categories?origin=china&department_id='.$department->id.'&is_active=1&per_page=100')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($clothing->id, $ids);
        $this->assertContains($tShirts->id, $ids);
    }

    public function test_tz_local_admin_categories_filter_by_store_and_active_flag_unchanged(): void
    {
        $this->seed(\Database\Seeders\StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $wigs = Category::query()->where('slug', 'rovi-beauty-wigs')->firstOrFail();
        $storeId = $wigs->store_id;

        $this->assertSame(CatalogOrigin::Tz, $wigs->origin);
        $this->assertTrue($wigs->is_active);

        $activeForStore = $this->getJson('/api/v1/admin/categories?origin=tz&store_id='.$storeId.'&is_active=1&per_page=100')
            ->assertOk()
            ->json('data');

        $activeSlugs = collect($activeForStore)->pluck('slug')->all();

        $this->assertContains('rovi-beauty-wigs', $activeSlugs);
        $this->assertTrue(
            collect($activeForStore)->every(fn (array $row) => ($row['store_id'] ?? null) === $storeId),
        );
    }

    public function test_storefront_china_menu_unchanged_when_department_categories_are_active(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(\Database\Seeders\CommerceChannelSeeder::class);

        Category::query()
            ->where('origin', CatalogOrigin::China)
            ->whereNotNull('department_id')
            ->update(['is_active' => true]);

        // Crosswalk visibility for bible roots mapped via department_slugs requires a
        // listable china product under that department — not a department starter root.
        $clothing = Category::query()->where('slug', 'mens-fashion-clothing-t-shirts')->firstOrFail();
        $channel = \App\Models\CommerceChannel::query()
            ->where('code', \App\Enums\CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $brand = \App\Models\Brand::factory()->create(['is_active' => true]);
        $product = \App\Models\Product::factory()->create([
            'category_id' => $clothing->id,
            'brand_id' => $brand->id,
            'commerce_channel_id' => $channel->id,
            'store_id' => null,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => \App\Enums\ProductLifecycleStatus::Active,
            'visibility' => \App\Enums\ProductVisibility::Public,
            'price' => 45000,
        ]);
        \App\Models\Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $response = $this->getJson('/api/v1/storefront/china/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $names = collect($response->json('data'))->pluck('name')->all();
        $bibleRoots = collect(CatalogBible::categories())->pluck('slug')->all();

        foreach ($slugs as $slug) {
            $this->assertContains($slug, $bibleRoots);
        }

        $this->assertContains('mens-fashion', $slugs);
        $this->assertNotContains('mens-fashion-clothing', $slugs);
        $this->assertNotContains('home-appliances-refrigerators-freezers', $slugs);
        $this->assertNotContains('Clothing', $names);
        $this->assertNotContains('T-Shirts', $names);
    }
}
