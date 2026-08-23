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
 * retires competing Consumer Electronics Power Banks categories/CPTs.
 */
class MobileAccessoriesTaxonomyCleanupService
{
    /**
     * @return array{
     *     dry_run: bool,
     *     canonical_category_id: string|null,
     *     canonical_product_type_id: string|null,
     *     competing: list<array<string, mixed>>,
     *     competing_product_types: list<array<string, mixed>>,
     *     anomalous_product_types: list<array<string, mixed>>,
     *     planned_migrations: list<array<string, mixed>>,
     *     migrated_product_ids: list<string>,
     *     deactivated_category_ids: list<string>,
     *     deactivated_product_type_ids: list<string>,
     *     skipped_category_ids: list<string>,
     *     skipped_product_type_ids: list<string>,
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
     *     canonical_product_type_id: string|null,
     *     competing: list<array<string, mixed>>,
     *     competing_product_types: list<array<string, mixed>>,
     *     anomalous_product_types: list<array<string, mixed>>,
     *     planned_migrations: list<array<string, mixed>>,
     *     migrated_product_ids: list<string>,
     *     deactivated_category_ids: list<string>,
     *     deactivated_product_type_ids: list<string>,
     *     skipped_category_ids: list<string>,
     *     skipped_product_type_ids: list<string>,
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

        $canonicalType = $this->resolveCanonicalProductType($canonical);
        if ($canonicalType === null) {
            throw new RuntimeException(
                'Canonical Power Bank product type is missing. Seed Phones & Tablets taxonomy first.',
            );
        }

        $competingCategories = $this->findCompetingPowerBankCategories($canonical);
        $competingTypes = $this->findCompetingPowerBankProductTypes($canonical, $canonicalType)
            ->reject(fn (CatalogProductType $type) => $competingCategories->contains('id', $type->subcategory_id))
            ->values();

        $steps = [
            'Canonical category: '.MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG,
            'Canonical CPT: '.MobileAccessoriesTaxonomy::CANONICAL_POWER_BANK_TYPE_SLUG,
        ];
        $migratedProductIds = [];
        $deactivatedCategoryIds = [];
        $deactivatedTypeIds = [];
        $skippedCategoryIds = [];
        $skippedTypeIds = [];
        $categoryReports = [];
        $typeReports = [];
        $anomalyReports = [];
        $plannedMigrations = [];

        foreach ($competingCategories as $category) {
            $report = $this->inspectCompetingCategory($category);
            $categoryReports[] = $report;

            if ($report['child_count'] > 0) {
                $skippedCategoryIds[] = $category->id;
                $steps[] = "Skipped category {$category->slug}: has {$report['child_count']} child categor(y/ies).";
                continue;
            }

            if ($dryRun) {
                $steps[] = $this->dryRunCategoryStep($category, $report);
                continue;
            }

            $this->repointCategoryDependencies($canonical, $canonicalType, $category, $report);
            $migratedProductIds = [...$migratedProductIds, ...$report['product_ids']];

            $category->is_active = false;
            $category->save();
            $category->delete();
            $deactivatedCategoryIds[] = $category->id;
            $steps[] = "Deactivated competing Power Banks category [{$category->slug}].";
        }

        foreach ($competingTypes as $type) {
            $status = $this->classifyCompetingProductType($type);

            if ($status === 'completed') {
                continue;
            }

            $report = $this->inspectCompetingProductType($type, $canonical, $canonicalType);
            $report['discovery_status'] = $status;

            if ($status === 'anomaly') {
                $anomalyReports[] = $report;
                $skippedTypeIds[] = $type->id;
                $steps[] = "Anomaly: inactive Power Banks CPT [{$type->slug}] still has {$report['product_count']} product(s); skipped automatic migration.";
                continue;
            }

            $typeReports[] = $report;
            $plannedMigrations = [...$plannedMigrations, ...$report['planned_migrations']];

            if (! $report['attribute_compatibility']['compatible']) {
                $skippedTypeIds[] = $type->id;
                $missing = implode(', ', $report['attribute_compatibility']['missing_required_on_competing']);
                $steps[] = "Skipped CPT {$type->slug}: canonical required attributes missing on competing type ({$missing}).";
                continue;
            }

            if ($dryRun) {
                $steps[] = $this->dryRunProductTypeStep($report);
                continue;
            }

            $this->migrateCompetingProductType($canonical, $canonicalType, $type, $report);
            $migratedProductIds = [...$migratedProductIds, ...$report['product_ids']];

            if ($this->competingProductTypeIsSafeToRetire($type)) {
                $type->is_active = false;
                $type->save();
                $deactivatedTypeIds[] = $type->id;
                $steps[] = "Deactivated competing Power Banks CPT [{$type->slug}]. Parent category [{$report['category_slug']}] preserved.";
            } else {
                $skippedTypeIds[] = $type->id;
                $steps[] = "Migrated products from CPT {$type->slug} but left it active: remaining dependencies.";
            }
        }

        if ($competingCategories->isEmpty() && $typeReports === [] && $anomalyReports === []) {
            $steps[] = 'No competing Power Banks categories or CPTs found.';
        }

        return [
            'dry_run' => $dryRun,
            'canonical_category_id' => $canonical->id,
            'canonical_product_type_id' => $canonicalType->id,
            'competing' => $categoryReports,
            'competing_product_types' => $typeReports,
            'anomalous_product_types' => $anomalyReports,
            'planned_migrations' => $plannedMigrations,
            'migrated_product_ids' => $migratedProductIds,
            'deactivated_category_ids' => $deactivatedCategoryIds,
            'deactivated_product_type_ids' => $deactivatedTypeIds,
            'skipped_category_ids' => $skippedCategoryIds,
            'skipped_product_type_ids' => $skippedTypeIds,
            'steps' => $steps,
        ];
    }

    public function resolveCanonicalProductType(Category $canonical): ?CatalogProductType
    {
        return CatalogProductType::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANK_TYPE_SLUG)
            ->first()
            ?? CatalogProductType::query()
                ->where('subcategory_id', $canonical->id)
                ->where('name', 'Power Bank')
                ->first();
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
     * @return Collection<int, CatalogProductType>
     */
    public function findCompetingPowerBankProductTypes(Category $canonical, CatalogProductType $canonicalType): Collection
    {
        return CatalogProductType::query()
            ->where('id', '!=', $canonicalType->id)
            ->where(function ($query): void {
                $query->whereIn('name', MobileAccessoriesTaxonomy::COMPETING_POWER_BANK_TYPE_NAMES)
                    ->orWhereIn('slug', MobileAccessoriesTaxonomy::FORBIDDEN_POWER_BANK_TYPE_SLUGS)
                    ->orWhere('slug', 'like', '%-power-bank')
                    ->orWhere('slug', 'like', '%-power-banks');
            })
            ->whereHas('subcategory', function ($query) use ($canonical): void {
                $query->whereNull('store_id')
                    ->where('origin', CatalogOrigin::China)
                    ->where('id', '!=', $canonical->id);
            })
            ->with(['subcategory.department'])
            ->get();
    }

    /**
     * @return 'actionable'|'completed'|'anomaly'
     */
    private function classifyCompetingProductType(CatalogProductType $type): string
    {
        $productCount = Product::query()
            ->where('catalog_product_type_id', $type->id)
            ->count();

        if ($type->is_active) {
            return 'actionable';
        }

        if ($productCount === 0) {
            return 'completed';
        }

        return 'anomaly';
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
    private function inspectCompetingCategory(Category $category): array
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
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     category_id: string|null,
     *     category_slug: string|null,
     *     category_name: string|null,
     *     department_slug: string|null,
     *     product_count: int,
     *     product_ids: list<string>,
     *     import_map_source_count: int,
     *     import_map_target_count: int,
     *     attribute_compatibility: array<string, mixed>,
     *     planned_migrations: list<array<string, mixed>>,
     *     target_category_id: string,
     *     target_category_slug: string,
     *     target_product_type_id: string,
     *     target_product_type_slug: string
     * }
     */
    private function inspectCompetingProductType(
        CatalogProductType $type,
        Category $canonical,
        CatalogProductType $canonicalType,
    ): array {
        $parent = $type->subcategory;
        $products = Product::query()
            ->where('catalog_product_type_id', $type->id)
            ->get(['id', 'name', 'slug', 'category_id', 'catalog_product_type_id', 'price']);

        $compatibility = $this->compareAttributeCompatibility($type, $canonicalType);
        $planned = [];
        foreach ($products as $product) {
            $planned[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'from_category_id' => $product->category_id,
                'from_category_slug' => $parent?->slug,
                'from_product_type_id' => $type->id,
                'from_product_type_slug' => $type->slug,
                'to_category_id' => $canonical->id,
                'to_category_slug' => $canonical->slug,
                'to_product_type_id' => $canonicalType->id,
                'to_product_type_slug' => $canonicalType->slug,
                'will_migrate' => $compatibility['compatible'],
            ];
        }

        return [
            'id' => $type->id,
            'slug' => $type->slug,
            'name' => $type->name,
            'category_id' => $parent?->id,
            'category_slug' => $parent?->slug,
            'category_name' => $parent?->name,
            'department_slug' => $parent?->department?->slug,
            'product_count' => $products->count(),
            'product_ids' => $products->pluck('id')->all(),
            'import_map_source_count' => $parent === null ? 0 : StoreTaxonomyImportMap::query()
                ->where('source_category_id', $parent->id)
                ->count(),
            'import_map_target_count' => $parent === null ? 0 : StoreTaxonomyImportMap::query()
                ->where('target_category_id', $parent->id)
                ->count(),
            'attribute_compatibility' => $compatibility,
            'planned_migrations' => $planned,
            'target_category_id' => $canonical->id,
            'target_category_slug' => $canonical->slug,
            'target_product_type_id' => $canonicalType->id,
            'target_product_type_slug' => $canonicalType->slug,
        ];
    }

    /**
     * @return array{
     *     compatible: bool,
     *     competing_attribute_slugs: list<string>,
     *     canonical_attribute_slugs: list<string>,
     *     competing_required_slugs: list<string>,
     *     canonical_required_slugs: list<string>,
     *     missing_required_on_competing: list<string>,
     *     extra_on_competing: list<string>
     * }
     */
    private function compareAttributeCompatibility(
        CatalogProductType $competing,
        CatalogProductType $canonical,
    ): array {
        $competing->loadMissing('attributes');
        $canonical->loadMissing('attributes');

        $competingSlugs = $competing->attributes->pluck('slug')->filter()->values()->all();
        $canonicalSlugs = $canonical->attributes->pluck('slug')->filter()->values()->all();
        $competingRequired = $competing->attributes
            ->filter(fn ($attribute) => (bool) ($attribute->pivot->is_required ?? false))
            ->pluck('slug')
            ->values()
            ->all();
        $canonicalRequired = $canonical->attributes
            ->filter(fn ($attribute) => (bool) ($attribute->pivot->is_required ?? false))
            ->pluck('slug')
            ->values()
            ->all();

        $missingRequired = array_values(array_diff($canonicalRequired, $competingSlugs));
        $extra = array_values(array_diff($competingSlugs, $canonicalSlugs));

        return [
            'compatible' => $missingRequired === [],
            'competing_attribute_slugs' => $competingSlugs,
            'canonical_attribute_slugs' => $canonicalSlugs,
            'competing_required_slugs' => $competingRequired,
            'canonical_required_slugs' => $canonicalRequired,
            'missing_required_on_competing' => $missingRequired,
            'extra_on_competing' => $extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function dryRunCategoryStep(Category $category, array $report): string
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
            return "Would deactivate empty competing Power Banks category [{$category->slug}].";
        }

        return "Would migrate ".implode(', ', $deps)." from category [{$category->slug}] then deactivate.";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function dryRunProductTypeStep(array $report): string
    {
        $parent = $report['category_slug'] ?? 'unknown';

        if ($report['product_count'] === 0) {
            return "Would deactivate empty competing Power Banks CPT [{$report['slug']}] under [{$parent}]. Parent category preserved.";
        }

        return sprintf(
            'Would migrate %d product(s) from CPT [%s] / category [%s] to CPT [%s] / category [%s]. Parent [%s] preserved.',
            $report['product_count'],
            $report['slug'],
            $parent,
            $report['target_product_type_slug'],
            $report['target_category_slug'],
            $parent,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function repointCategoryDependencies(
        Category $canonical,
        CatalogProductType $canonicalType,
        Category $competing,
        array $report,
    ): void {
        Product::query()
            ->where('category_id', $competing->id)
            ->update([
                'category_id' => $canonical->id,
                'catalog_product_type_id' => $canonicalType->id,
            ]);

        foreach ($report['catalog_product_type_ids'] as $typeId) {
            $type = CatalogProductType::query()->find($typeId);
            if ($type === null || $type->id === $canonicalType->id) {
                continue;
            }

            Product::query()
                ->where('catalog_product_type_id', $type->id)
                ->update([
                    'category_id' => $canonical->id,
                    'catalog_product_type_id' => $canonicalType->id,
                ]);
            $type->is_active = false;
            $type->save();
            $type->delete();
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

    /**
     * @param  array<string, mixed>  $report
     */
    private function migrateCompetingProductType(
        Category $canonical,
        CatalogProductType $canonicalType,
        CatalogProductType $competing,
        array $report,
    ): void {
        Product::query()
            ->where('catalog_product_type_id', $competing->id)
            ->update([
                'category_id' => $canonical->id,
                'catalog_product_type_id' => $canonicalType->id,
            ]);

        // Parent Mobile Accessories / other accessory roots keep their import maps.
        unset($report);
    }

    private function competingProductTypeIsSafeToRetire(CatalogProductType $type): bool
    {
        $type->refresh();

        return Product::query()->where('catalog_product_type_id', $type->id)->doesntExist();
    }
}
