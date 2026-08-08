<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Services\Catalog\HomeCareTaxonomyRestructureService;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeCareTaxonomyRestructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_restructure_restores_home_care_hierarchy_and_fixes_product_type(): void
    {
        $fixture = $this->seedSoftDeletedHomeCareFixture();

        $result = app(HomeCareTaxonomyRestructureService::class)->restructure(dryRun: false);

        $this->assertFalse($result['dry_run']);
        $this->assertSame($fixture['department']->id, $result['department_id']);
        $this->assertContains('flavour', $result['removed_attribute_slugs']);
        $this->assertEqualsCanonicalizing(
            ['cleaning-hygiene', 'household-essentials', 'smart-home-care'],
            $result['created_category_slugs'],
        );

        $department = Department::query()->where('slug', 'home-care')->first();
        $this->assertNotNull($department);
        $this->assertNull($department->deleted_at);
        $this->assertTrue($department->is_active);

        $bibleRoot = Category::query()->where('slug', 'home-care')->first();
        $this->assertNotNull($bibleRoot);
        $this->assertNull($bibleRoot->department_id);
        $this->assertNull($bibleRoot->store_id);
        $this->assertNull($bibleRoot->parent_id);
        $this->assertSame($bibleRoot->id, $result['bible_root_id']);

        $categories = Category::query()
            ->where('department_id', $department->id)
            ->where('parent_id', $bibleRoot->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertSame(
            ['pest-control', 'cleaning-hygiene', 'household-essentials', 'smart-home-care'],
            $categories->pluck('slug')->all(),
        );

        $this->assertTrue($categories->every(
            fn (Category $category) => $category->origin === CatalogOrigin::China
                && $category->store_id === null
                && $category->is_active
                && $category->parent_id === $bibleRoot->id,
        ));

        $pestControl = $categories->firstWhere('slug', 'pest-control');
        $this->assertNotNull($pestControl);
        $this->assertSame(0, Category::query()->where('parent_id', $pestControl->id)->count());

        $duplicate = Category::withTrashed()->where('slug', 'pest-control-pest-control')->first();
        $this->assertNotNull($duplicate);
        $this->assertNotNull($duplicate->deleted_at);

        $productType = CatalogProductType::query()
            ->where('slug', 'pest-control-insecticide-spray')
            ->first();

        $this->assertNotNull($productType);
        $this->assertNull($productType->deleted_at);
        $this->assertSame('Insecticide Spray', $productType->name);
        $this->assertSame($pestControl->id, $productType->subcategory_id);

        $mappedSlugs = $productType->attributes()->pluck('catalog_attributes.slug')->all();
        $this->assertContains('volume', $mappedSlugs);
        $this->assertNotContains('flavour', $mappedSlugs);

        $this->assertSame(
            0,
            CatalogProductType::withTrashed()
                ->where('subcategory_id', $duplicate->id)
                ->count(),
        );
    }

    public function test_restructure_is_idempotent(): void
    {
        $this->seedSoftDeletedHomeCareFixture();

        $service = app(HomeCareTaxonomyRestructureService::class);
        $service->restructure(dryRun: false);
        $second = $service->restructure(dryRun: false);

        $this->assertSame([], $second['created_category_slugs']);
        $this->assertSame(
            4,
            Category::query()
                ->where('department_id', $second['department_id'])
                ->where('parent_id', $second['bible_root_id'])
                ->whereNull('deleted_at')
                ->count(),
        );
        $this->assertSame(
            1,
            CatalogProductType::query()->where('slug', 'pest-control-insecticide-spray')->count(),
        );
    }

    public function test_dry_run_does_not_mutate_database(): void
    {
        $fixture = $this->seedSoftDeletedHomeCareFixture();

        app(HomeCareTaxonomyRestructureService::class)->restructure(dryRun: true);

        $this->assertNotNull(Department::withTrashed()->find($fixture['department']->id)?->deleted_at);
        $this->assertNotNull(Category::withTrashed()->find($fixture['pestControl']->id)?->deleted_at);
        $this->assertNotNull(CatalogProductType::withTrashed()->find($fixture['productType']->id)?->deleted_at);
        $this->assertNull(Category::query()->where('slug', 'cleaning-hygiene')->first());
    }

    public function test_restructure_does_not_mutate_catalog_bible_home_care_root(): void
    {
        $before = collect(CatalogBible::categories())->pluck('slug')->all();
        $this->assertContains('home-care', $before);

        $this->seedSoftDeletedHomeCareFixture();
        app(HomeCareTaxonomyRestructureService::class)->restructure(dryRun: false);

        $after = collect(CatalogBible::categories())->pluck('slug')->all();
        $this->assertSame($before, $after);
        $this->assertContains('home-care', $after);
    }

    public function test_artisan_command_execute_flag(): void
    {
        $this->seedSoftDeletedHomeCareFixture();

        $this->artisan('catalog:restructure-home-care')
            ->assertSuccessful();

        $this->assertNull(Department::query()->where('slug', 'home-care')->first());

        $this->artisan('catalog:restructure-home-care', ['--execute' => true])
            ->assertSuccessful();

        $this->assertNotNull(Department::query()->where('slug', 'home-care')->first());
    }

    /**
     * @return array{
     *     department: Department,
     *     pestControl: Category,
     *     duplicateChild: Category,
     *     productType: CatalogProductType
     * }
     */
    private function seedSoftDeletedHomeCareFixture(): array
    {
        $department = Department::factory()->create([
            'name' => 'Home Care',
            'slug' => 'home-care',
            'sort_order' => 18,
            'is_active' => true,
        ]);

        $pestControl = Category::factory()->create([
            'department_id' => $department->id,
            'name' => 'Pest Control',
            'slug' => 'pest-control',
            'parent_id' => null,
            'origin' => CatalogOrigin::China,
            'store_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $duplicateChild = Category::factory()->create([
            'department_id' => $department->id,
            'name' => 'Pest Control',
            'slug' => 'pest-control-pest-control',
            'parent_id' => $pestControl->id,
            'origin' => CatalogOrigin::China,
            'store_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $productType = CatalogProductType::factory()->create([
            'subcategory_id' => $duplicateChild->id,
            'name' => 'Insectcide Spray',
            'slug' => 'pest-control-pest-control-insectcide-spray',
            'is_active' => true,
        ]);

        $flavour = CatalogAttribute::factory()->create([
            'name' => 'Flavour',
            'slug' => 'flavour',
        ]);
        $volume = CatalogAttribute::factory()->create([
            'name' => 'Volume',
            'slug' => 'volume',
        ]);

        $productType->attributes()->sync([
            $flavour->id => ['is_required' => false, 'sort_order' => 1],
            $volume->id => ['is_required' => false, 'sort_order' => 2],
        ]);

        $productType->delete();
        $duplicateChild->delete();
        $pestControl->delete();
        $department->delete();

        $this->assertNotNull(Department::withTrashed()->find($department->id)?->deleted_at);
        $this->assertSame(
            2,
            DB::table('catalog_product_type_attributes')
                ->where('catalog_product_type_id', $productType->id)
                ->count(),
        );

        return [
            'department' => $department,
            'pestControl' => $pestControl,
            'duplicateChild' => $duplicateChild,
            'productType' => $productType,
        ];
    }
}
