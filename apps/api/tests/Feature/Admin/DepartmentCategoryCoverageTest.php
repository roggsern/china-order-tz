<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ensures every CHINA_IMPORT department has department-backed admin categories.
 */
class DepartmentCategoryCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_every_department_has_at_least_one_china_category(): void
    {
        $this->seedTaxonomy();

        foreach (DepartmentSeeder::definitions() as $definition) {
            $slug = Str::slug($definition['name']);
            $department = Department::query()->where('slug', $slug)->firstOrFail();

            $count = Category::query()
                ->where('origin', CatalogOrigin::China)
                ->where('department_id', $department->id)
                ->whereNull('deleted_at')
                ->count();

            $this->assertGreaterThan(
                0,
                $count,
                "Department [{$slug}] must have at least one china category with matching department_id.",
            );
        }
    }

    public function test_admin_api_returns_non_empty_categories_for_previously_empty_departments(): void
    {
        $this->seedTaxonomy();

        $slugs = [
            'home-appliances',
            'home-furniture',
            'beauty-personal-care',
            'automotive',
            'groceries',
            'professional-audio',
        ];

        foreach ($slugs as $slug) {
            $department = Department::query()->where('slug', $slug)->firstOrFail();

            $response = $this->getJson(
                '/api/v1/admin/categories?origin=china&department_id='.$department->id.'&per_page=100',
            )
                ->assertOk()
                ->assertJsonPath('success', true);

            $data = collect($response->json('data'));

            $this->assertNotEmpty(
                $data->all(),
                "Admin categories API must return rows for department [{$slug}].",
            );

            $this->assertTrue(
                $data->every(fn (array $row) => ($row['department_id'] ?? null) === $department->id),
                "Admin categories for [{$slug}] must only include that department_id.",
            );
        }
    }

    public function test_department_category_seeders_are_idempotent(): void
    {
        $this->seedTaxonomy();

        $countAfterFirst = Category::query()->count();
        $slugCountAfterFirst = Category::query()->pluck('slug')->unique()->count();

        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $this->assertSame($countAfterFirst, Category::query()->count());
        $this->assertSame($slugCountAfterFirst, Category::query()->pluck('slug')->unique()->count());
    }

    public function test_reseeding_does_not_change_existing_product_category_id(): void
    {
        $this->seedTaxonomy();

        $category = Category::query()
            ->where('slug', 'home-appliances-refrigerators-freezers')
            ->firstOrFail();

        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        $originalCategoryId = $product->category_id;

        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $this->assertSame($originalCategoryId, $product->fresh()->category_id);
        $this->assertSame(
            $category->id,
            Category::query()->where('slug', 'home-appliances-refrigerators-freezers')->value('id'),
        );
    }

    public function test_department_starter_categories_do_not_become_storefront_china_roots(): void
    {
        $this->seedTaxonomy();

        Category::query()
            ->where('origin', CatalogOrigin::China)
            ->whereNotNull('department_id')
            ->update(['is_active' => true]);

        $response = $this->getJson('/api/v1/storefront/china/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $bibleRoots = collect(CatalogBible::categories())->pluck('slug')->all();

        foreach ($slugs as $slug) {
            $this->assertContains($slug, $bibleRoots);
        }

        $this->assertNotContains('home-appliances-refrigerators-freezers', $slugs);
        $this->assertNotContains('groceries-snacks', $slugs);
        $this->assertNotContains('professional-audio-speakers', $slugs);
    }

    public function test_department_categories_map_covers_all_department_seeder_slugs(): void
    {
        $departmentSlugs = collect(DepartmentSeeder::definitions())
            ->map(fn (array $definition) => Str::slug($definition['name']))
            ->sort()
            ->values()
            ->all();

        $coveredSlugs = collect(array_keys(CategorySeeder::departmentCategories()))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $departmentSlugs,
            $coveredSlugs,
            'CategorySeeder::departmentCategories() must cover every DepartmentSeeder slug.',
        );
    }

    private function seedTaxonomy(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
    }
}
