<?php

namespace App\Services\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\StoreTaxonomyImportMap;
use App\Support\Catalog\MobileAccessoriesTaxonomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reuses Phones & Tablets → Phone Accessories → Power Banks and
 * deactivates competing Consumer Electronics / flat Power Banks nodes.
 */
class MobileAccessoriesTaxonomyCleanupService
{
    /**
     * @return array{
     *     dry_run: bool,
     *     canonical_category_id: string|null,
     *     competing: list<array<string, mixed>>,
     *     migrated_product_ids: list<string>,
     *     deactivated_category_ids: list<string>,
     *     skipped_category_ids: list<string>,
     *     steps: list<string>
     * }
     */
    public function cleanup(bool $dryRun = true): array
    {
        if ($dryRun) {
            return $this->buildPlan(dryRun: true);
        }

        return DB::transaction(fn () => $this->buildPlan(dryRun: false));
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     canonical_category_id: string|null,
     *     competing: list<array<string, mixed>>,
     *     migrated_product_ids: list<string>,
     *     deactivated_category_ids: list<string>,
     *     skipped_category_ids: list<string>,
     *     steps: list<string>
     * }
     */
    private function buildPlan(bool $dryRun): array
    {
        $canonical = Category::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)
            ->first();

        if ($canonical === null) {
            throw new RuntimeException(
                'Canonical Power Banks leaf is missing. Seed Phones & Tablets taxonomy first.',
            );
        }

        $competing = $this->findCompetingPowerBankCategories($canonical);
        $steps = [
            'Canonical Power Banks: '.MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG,
        ];
        $migratedProductIds = [];
        $deactivatedIds = [];
        $skippedIds = [];
        $reports = [];

        foreach ($competing as $category) {
            $report = $this->inspectCompeting($category);
            $reports[] = $report;

            if ($report['child_count'] > 0) {
                $skippedIds[] = $category->id;
                $steps[] = "Skipped {$category->slug}: has {$report['child_count']} child categor(y/ies).";
                continue;
            }

            if ($dryRun) {
                $steps[] = $this->dryRunStep($category, $report);
                continue;
            }

            $this->repointDependencies($canonical, $category, $report);
            $migratedProductIds = [...$migratedProductIds, ...$report['product_ids']];

            $category->is_active = false;
            $category->save();
            $category->delete();
            $deactivatedIds[] = $category->id;
            $steps[] = "Deactivated competing Power Banks [{$category->slug}].";
        }

        if ($competing->isEmpty()) {
            $steps[] = 'No competing Power Banks nodes found.';
        }

        return [
            'dry_run' => $dryRun,
            'canonical_category_id' => $canonical->id,
            'competing' => $reports,
            'migrated_product_ids' => $migratedProductIds,
            'deactivated_category_ids' => $deactivatedIds,
            'skipped_category_ids' => $skippedIds,
            'steps' => $steps,
        ];
    }

    /**
     * @return Collection<int, Category>
     */
    public function findCompetingPowerBankCategories(Category $canonical): Collection
    {
        $accessoriesId = Category::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_ACCESSORIES_SLUG)
            ->value('id');
        $consumerElectronicsId = Department::query()
            ->where('slug', MobileAccessoriesTaxonomy::CONSUMER_ELECTRONICS_DEPARTMENT_SLUG)
            ->value('id');

        return Category::query()
            ->whereNull('store_id')
            ->where('origin', CatalogOrigin::China)
            ->where('id', '!=', $canonical->id)
            ->where(function ($query) use ($accessoriesId, $consumerElectronicsId): void {
                $query->whereIn('slug', MobileAccessoriesTaxonomy::FORBIDDEN_POWER_BANK_SLUGS)
                    ->orWhere(function ($inner) use ($consumerElectronicsId): void {
                        $inner->where('name', MobileAccessoriesTaxonomy::POWER_BANKS_NAME)
                            ->where('department_id', $consumerElectronicsId);
                    })
                    ->orWhere(function ($inner) use ($accessoriesId): void {
                        $inner->where('name', MobileAccessoriesTaxonomy::POWER_BANKS_NAME)
                            ->where(function ($parentQuery) use ($accessoriesId): void {
                                $parentQuery->whereNull('parent_id');
                                if ($accessoriesId !== null) {
                                    $parentQuery->orWhere('parent_id', '!=', $accessoriesId);
                                }
                            });
                    });
            })
            ->get();
    }

    /**
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     department_id: string|null,
     *     parent_id: string|null,
     *     product_count: int,
     *     product_ids: list<string>,
     *     pivot_product_count: int,
     *     catalog_product_type_count: int,
     *     catalog_product_type_ids: list<string>,
     *     import_map_source_count: int,
     *     import_map_target_count: int,
     *     child_count: int
     * }
     */
    private function inspectCompeting(Category $category): array
    {
        $productIds = Product::query()
            ->where('category_id', $category->id)
            ->pluck('id')
            ->all();
        $typeIds = CatalogProductType::query()
            ->where('subcategory_id', $category->id)
            ->pluck('id')
            ->all();

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'department_id' => $category->department_id,
            'parent_id' => $category->parent_id,
            'product_count' => count($productIds),
            'product_ids' => $productIds,
            'pivot_product_count' => (int) $category->catalogProducts()->count(),
            'catalog_product_type_count' => count($typeIds),
            'catalog_product_type_ids' => $typeIds,
            'import_map_source_count' => StoreTaxonomyImportMap::query()
                ->where('source_category_id', $category->id)
                ->count(),
            'import_map_target_count' => StoreTaxonomyImportMap::query()
                ->where('target_category_id', $category->id)
                ->count(),
            'child_count' => Category::query()->where('parent_id', $category->id)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function dryRunStep(Category $category, array $report): string
    {
        $deps = [];
        if ($report['product_count'] > 0) {
            $deps[] = $report['product_count'].' product(s)';
        }
        if ($report['catalog_product_type_count'] > 0) {
            $deps[] = $report['catalog_product_type_count'].' CPT(s)';
        }
        if ($report['import_map_source_count'] > 0 || $report['import_map_target_count'] > 0) {
            $deps[] = 'import map(s)';
        }

        if ($deps === []) {
            return "Would deactivate empty competing Power Banks [{$category->slug}].";
        }

        return "Would migrate ".implode(', ', $deps)." from [{$category->slug}] then deactivate.";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function repointDependencies(Category $canonical, Category $competing, array $report): void
    {
        Product::query()
            ->where('category_id', $competing->id)
            ->update(['category_id' => $canonical->id]);

        $canonicalTypeId = CatalogProductType::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANK_TYPE_SLUG)
            ->value('id')
            ?? CatalogProductType::query()
                ->where('subcategory_id', $canonical->id)
                ->where('name', 'Power Bank')
                ->value('id');

        foreach ($report['catalog_product_type_ids'] as $typeId) {
            $type = CatalogProductType::query()->find($typeId);
            if ($type === null) {
                continue;
            }

            if ($canonicalTypeId !== null && $type->id !== $canonicalTypeId) {
                Product::query()
                    ->where('catalog_product_type_id', $type->id)
                    ->update(['catalog_product_type_id' => $canonicalTypeId]);
                $type->delete();
                continue;
            }

            $type->subcategory_id = $canonical->id;
            $type->save();
        }

        $maps = StoreTaxonomyImportMap::query()
            ->where('source_category_id', $competing->id)
            ->get();

        foreach ($maps as $map) {
            $alreadyMapped = StoreTaxonomyImportMap::query()
                ->where('store_id', $map->store_id)
                ->where('source_category_id', $canonical->id)
                ->where('id', '!=', $map->id)
                ->exists();

            if ($alreadyMapped) {
                $map->delete();
                continue;
            }

            $map->source_category_id = $canonical->id;
            $map->save();
        }

        if ($report['pivot_product_count'] > 0) {
            $productIds = $competing->catalogProducts()->pluck('products.id');
            $competing->catalogProducts()->detach();
            $canonical->catalogProducts()->syncWithoutDetaching($productIds->all());
        }
    }
}
