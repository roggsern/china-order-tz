<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CoreDatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ensures every CHINA_IMPORT department with categories has Catalog Product Types.
 */
class ChinaCatalogProductTypeCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const PREVIOUSLY_COMPLETE_DEPARTMENTS = [
        'computers-office',
        'consumer-electronics',
        'mens-fashion',
        'phones-tablets',
        'professional-audio',
        'womens-fashion',
    ];

    public function test_every_china_import_department_has_catalog_product_types(): void
    {
        $this->seedChinaTaxonomyWithTypes();

        foreach (DepartmentSeeder::definitions() as $definition) {
            $slug = Str::slug($definition['name']);
            $department = Department::query()->where('slug', $slug)->firstOrFail();

            $categoryCount = Category::query()
                ->where('origin', CatalogOrigin::China)
                ->where('department_id', $department->id)
                ->whereNull('deleted_at')
                ->count();

            $this->assertGreaterThan(0, $categoryCount, "Department [{$slug}] must have categories.");

            $typeCount = CatalogProductType::query()
                ->whereNull('deleted_at')
                ->whereHas(
                    'subcategory',
                    fn ($query) => $query
                        ->where('department_id', $department->id)
                        ->where('origin', CatalogOrigin::China),
                )
                ->count();

            $this->assertGreaterThan(
                0,
                $typeCount,
                "Department [{$slug}] must have at least one Catalog Product Type.",
            );
        }
    }

    public function test_every_eligible_china_category_has_at_least_one_product_type(): void
    {
        $this->seedChinaTaxonomyWithTypes();

        // Eligible = department-backed leaf categories (wizard attachment points).
        // CatalogBible mega-menu roots are not product-type parents.
        // Duplicate department roots superseded by same-named nested categories are not eligible
        // (CatalogProductTypeSeeder resolves nested parents first).
        $eligibleCategories = Category::query()
            ->where('origin', CatalogOrigin::China)
            ->whereNotNull('department_id')
            ->whereNull('deleted_at')
            ->whereDoesntHave('children')
            ->orderBy('slug')
            ->get()
            ->filter(function (Category $category): bool {
                if ($category->parent_id !== null) {
                    return true;
                }

                return ! Category::query()
                    ->where('department_id', $category->department_id)
                    ->where('name', $category->name)
                    ->whereNotNull('parent_id')
                    ->whereNull('deleted_at')
                    ->exists();
            })
            ->values();

        $this->assertNotEmpty($eligibleCategories);

        $uncovered = [];

        foreach ($eligibleCategories as $category) {
            $typeCount = CatalogProductType::query()
                ->where('subcategory_id', $category->id)
                ->whereNull('deleted_at')
                ->count();

            if ($typeCount === 0) {
                $uncovered[] = $category->slug;
            }
        }

        $this->assertSame(
            [],
            $uncovered,
            'Every eligible department leaf category must have at least one Catalog Product Type.',
        );
    }

    public function test_no_china_catalog_product_type_references_wrong_journey(): void
    {
        $this->seed(CoreDatabaseSeeder::class);

        $chinaTypes = CatalogProductType::query()
            ->whereNull('deleted_at')
            ->whereHas(
                'subcategory',
                fn ($query) => $query->whereNotNull('department_id'),
            )
            ->with('subcategory')
            ->get();

        $this->assertNotEmpty($chinaTypes);

        foreach ($chinaTypes as $type) {
            $this->assertSame(
                CatalogOrigin::China,
                $type->subcategory->origin,
                "China department Catalog Product Type [{$type->slug}] must reference a CHINA_IMPORT category.",
            );
        }

        $tzTypes = CatalogProductType::query()
            ->whereNull('deleted_at')
            ->whereHas('subcategory', fn ($query) => $query->where('origin', CatalogOrigin::Tz))
            ->with('subcategory')
            ->get();

        $this->assertNotEmpty($tzTypes);

        foreach ($tzTypes as $type) {
            $this->assertSame(
                CatalogOrigin::Tz,
                $type->subcategory->origin,
                "TZ Catalog Product Type [{$type->slug}] must reference a TZ_LOCAL category.",
            );
            $this->assertNull(
                $type->subcategory->department_id,
                "TZ Catalog Product Type [{$type->slug}] must not attach to a China department category.",
            );
        }
    }

    public function test_catalog_product_type_seeder_is_idempotent(): void
    {
        $this->seedChinaTaxonomyWithTypes();

        $countAfterFirst = CatalogProductType::query()->whereNull('deleted_at')->count();
        $idsAfterFirst = CatalogProductType::query()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->seed(CatalogProductTypeSeeder::class);

        $this->assertSame($countAfterFirst, CatalogProductType::query()->whereNull('deleted_at')->count());
        $this->assertSame(
            $idsAfterFirst,
            CatalogProductType::query()->whereNull('deleted_at')->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_previously_existing_product_types_remain_unchanged(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $baselineDefinitions = collect(CatalogProductTypeSeeder::definitions())
            ->only(self::PREVIOUSLY_COMPLETE_DEPARTMENTS)
            ->all();

        // Seed once with only the previously complete department definitions conceptually:
        // full seeder includes new depts; capture IDs for types under the six complete depts.
        $this->seed(CatalogProductTypeSeeder::class);

        $existing = CatalogProductType::query()
            ->whereNull('deleted_at')
            ->whereHas(
                'subcategory.department',
                fn ($query) => $query->whereIn('slug', self::PREVIOUSLY_COMPLETE_DEPARTMENTS),
            )
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'subcategory_id', 'sort_order', 'is_active']);

        $this->assertNotEmpty($existing);
        $this->assertNotEmpty($baselineDefinitions);

        $snapshot = $existing->map(fn (CatalogProductType $type) => [
            'id' => $type->id,
            'slug' => $type->slug,
            'name' => $type->name,
            'subcategory_id' => $type->subcategory_id,
            'sort_order' => $type->sort_order,
            'is_active' => $type->is_active,
        ])->all();

        $this->seed(CatalogProductTypeSeeder::class);

        foreach ($snapshot as $row) {
            $fresh = CatalogProductType::query()->findOrFail($row['id']);

            $this->assertSame($row['slug'], $fresh->slug);
            $this->assertSame($row['name'], $fresh->name);
            $this->assertSame($row['subcategory_id'], $fresh->subcategory_id);
            $this->assertSame($row['sort_order'], $fresh->sort_order);
            $this->assertSame($row['is_active'], $fresh->is_active);
        }
    }

    private function seedChinaTaxonomyWithTypes(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
    }
}
