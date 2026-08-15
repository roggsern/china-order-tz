<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Services\Catalog\HomeCareTaxonomyRestructureService;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\HomeCareOperationalTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fresh-seed Home Care operational taxonomy parity (distinct from CatalogBible root).
 */
class HomeCareOperationalTaxonomyParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_produces_one_operational_home_care_department(): void
    {
        $this->seedOperationalHomeCare();

        $this->assertSame(1, Department::query()->where('slug', 'home-care')->count());
        $this->assertSame(1, Department::withTrashed()->where('slug', 'home-care')->count());

        $department = Department::query()->where('slug', 'home-care')->firstOrFail();
        $this->assertTrue($department->is_active);
        $this->assertSame('Home Care', $department->name);
    }

    public function test_home_care_operational_categories_retain_correct_department_id(): void
    {
        $this->seedOperationalHomeCare();

        $department = Department::query()->where('slug', 'home-care')->firstOrFail();
        $bibleRoot = Category::query()->where('slug', 'home-care')->firstOrFail();

        $this->assertNull($bibleRoot->department_id);
        $this->assertNull($bibleRoot->parent_id);
        $this->assertSame(CatalogOrigin::China, $bibleRoot->origin);

        $children = Category::query()
            ->where('department_id', $department->id)
            ->where('parent_id', $bibleRoot->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertSame(
            ['pest-control', 'cleaning-hygiene', 'household-essentials', 'smart-home-care'],
            $children->pluck('slug')->all(),
        );

        $this->assertTrue($children->every(
            fn (Category $category) => $category->origin === CatalogOrigin::China
                && $category->store_id === null
                && $category->is_active
                && $category->department_id === $department->id
                && $category->parent_id === $bibleRoot->id,
        ));
    }

    public function test_catalog_bible_home_care_root_remains_separate_from_department(): void
    {
        $this->seedOperationalHomeCare();

        $bibleRoot = Category::query()->where('slug', 'home-care')->firstOrFail();
        $department = Department::query()->where('slug', 'home-care')->firstOrFail();

        $this->assertNotSame($bibleRoot->id, $department->id);
        $this->assertNull($bibleRoot->department_id);
        $this->assertSame(1, Category::query()->where('slug', 'home-care')->count());
        $this->assertSame(1, Department::query()->where('slug', 'home-care')->count());
    }

    public function test_home_care_seed_and_restructure_are_idempotent(): void
    {
        $this->seedOperationalHomeCare();

        $childSlugs = collect(HomeCareTaxonomyRestructureService::siblingCategoryDefinitions())
            ->pluck('slug')
            ->all();

        $departmentCount = Department::query()->where('slug', 'home-care')->count();
        $bibleRootCount = Category::query()->where('slug', 'home-care')->count();
        $childCount = Category::query()->whereIn('slug', $childSlugs)->count();
        $typeCount = CatalogProductType::query()
            ->where('slug', HomeCareTaxonomyRestructureService::PRODUCT_TYPE_SLUG)
            ->count();

        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(HomeCareOperationalTaxonomySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);

        $this->assertSame($departmentCount, Department::query()->where('slug', 'home-care')->count());
        $this->assertSame($bibleRootCount, Category::query()->where('slug', 'home-care')->count());
        $this->assertSame($childCount, Category::query()->whereIn('slug', $childSlugs)->count());
        $this->assertSame(
            $typeCount,
            CatalogProductType::query()->where('slug', HomeCareTaxonomyRestructureService::PRODUCT_TYPE_SLUG)->count(),
        );

        $result = app(HomeCareTaxonomyRestructureService::class)->restructure(dryRun: false);

        $this->assertSame([], $result['created_category_slugs']);
        $this->assertSame($childCount, Category::query()->whereIn('slug', $childSlugs)->count());
        $this->assertSame(1, Department::query()->where('slug', 'home-care')->count());
        $this->assertSame(1, Category::query()->where('slug', 'home-care')->whereNull('department_id')->count());
    }

    public function test_home_care_product_types_attach_to_operational_leaves(): void
    {
        $this->seedOperationalHomeCare();

        $expected = [
            'pest-control' => 'pest-control-insecticide-spray',
            'cleaning-hygiene' => 'cleaning-hygiene-cleaning-product',
            'household-essentials' => 'household-essentials-household-essential',
            'smart-home-care' => 'smart-home-care-smart-home-device',
        ];

        foreach ($expected as $categorySlug => $typeSlug) {
            $category = Category::query()->where('slug', $categorySlug)->firstOrFail();
            $type = CatalogProductType::query()->where('slug', $typeSlug)->firstOrFail();
            $this->assertSame($category->id, $type->subcategory_id);
            $this->assertTrue($type->is_active);
        }
    }

    private function seedOperationalHomeCare(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(HomeCareOperationalTaxonomySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
    }
}
