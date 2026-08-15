<?php

namespace Database\Seeders;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Department;
use App\Services\Catalog\HomeCareCatalogBibleRootService;
use App\Services\Catalog\HomeCareTaxonomyRestructureService;
use Illuminate\Database\Seeder;

/**
 * Seeds the operational Home Care department leaves under the CatalogBible root.
 *
 * Distinct from CatalogBible category slug `home-care` (department_id=null chrome).
 * Complements DepartmentSeeder + CategorySeeder so fresh envs match production
 * without requiring catalog:restructure-home-care as a bootstrap step.
 */
class HomeCareOperationalTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::query()
            ->where('slug', HomeCareTaxonomyRestructureService::DEPARTMENT_SLUG)
            ->first();

        if ($department === null) {
            return;
        }

        $bibleRootService = app(HomeCareCatalogBibleRootService::class);
        $bibleRootService->ensureRoot(dryRun: false);

        $bibleRoot = Category::query()
            ->where('slug', HomeCareCatalogBibleRootService::ROOT_SLUG)
            ->first();

        if ($bibleRoot === null) {
            return;
        }

        foreach (HomeCareTaxonomyRestructureService::siblingCategoryDefinitions() as $definition) {
            $existing = Category::withTrashed()
                ->where('slug', $definition['slug'])
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'department_id' => $department->id,
                    'parent_id' => $bibleRoot->id,
                    'store_id' => null,
                    'origin' => CatalogOrigin::China,
                    'name' => $definition['name'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]);

                continue;
            }

            Category::query()->create([
                'department_id' => $department->id,
                'store_id' => null,
                'parent_id' => $bibleRoot->id,
                'origin' => CatalogOrigin::China,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => null,
                'image' => null,
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
            ]);
        }

        $bibleRootService->ensure(dryRun: false);
    }
}
