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

        Category::query()
            ->where('origin', CatalogOrigin::China)
            ->whereNotNull('department_id')
            ->update(['is_active' => true]);

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
        $this->assertNotContains('Clothing', $names);
        $this->assertNotContains('T-Shirts', $names);
    }
}
