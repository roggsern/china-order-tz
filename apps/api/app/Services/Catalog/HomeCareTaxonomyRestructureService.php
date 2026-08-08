<?php

namespace App\Services\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Restores and restructures the soft-deleted Home Care China taxonomy.
 *
 * Does not touch CatalogBible, navigation crosswalk, or storefront surfaces.
 */
class HomeCareTaxonomyRestructureService
{
    public const DEPARTMENT_SLUG = 'home-care';

    public const PEST_CONTROL_SLUG = 'pest-control';

    public const DUPLICATE_CHILD_SLUG = 'pest-control-pest-control';

    public const PRODUCT_TYPE_LEGACY_SLUG = 'pest-control-pest-control-insectcide-spray';

    public const PRODUCT_TYPE_NAME = 'Insecticide Spray';

    public const PRODUCT_TYPE_SLUG = 'pest-control-insecticide-spray';

    public const FLAVOUR_ATTRIBUTE_SLUG = 'flavour';

    public const VOLUME_ATTRIBUTE_SLUG = 'volume';

    /**
     * @return list<array{name: string, slug: string, sort_order: int}>
     */
    public static function siblingCategoryDefinitions(): array
    {
        return [
            ['name' => 'Pest Control', 'slug' => self::PEST_CONTROL_SLUG, 'sort_order' => 10],
            ['name' => 'Cleaning & Hygiene', 'slug' => 'cleaning-hygiene', 'sort_order' => 20],
            ['name' => 'Household Essentials', 'slug' => 'household-essentials', 'sort_order' => 30],
            ['name' => 'Smart Home Care', 'slug' => 'smart-home-care', 'sort_order' => 40],
        ];
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     steps: list<string>,
     *     department_id: string,
     *     pest_control_id: string,
     *     product_type_id: string,
     *     removed_duplicate_category_id: string|null,
     *     created_category_slugs: list<string>,
     *     removed_attribute_slugs: list<string>
     * }
     */
    public function restructure(bool $dryRun = true): array
    {
        if ($dryRun) {
            return $this->buildPlan(dryRun: true);
        }

        return DB::transaction(fn () => $this->buildPlan(dryRun: false));
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     steps: list<string>,
     *     department_id: string,
     *     pest_control_id: string,
     *     product_type_id: string,
     *     removed_duplicate_category_id: string|null,
     *     created_category_slugs: list<string>,
     *     removed_attribute_slugs: list<string>
     * }
     */
    private function buildPlan(bool $dryRun): array
    {
        $steps = [];
        $createdCategorySlugs = [];
        $removedAttributeSlugs = [];

        $department = Department::withTrashed()
            ->where('slug', self::DEPARTMENT_SLUG)
            ->first();

        if ($department === null) {
            throw new RuntimeException(
                'Home Care department (slug=home-care) was not found, including soft-deleted rows.',
            );
        }

        if ($department->trashed()) {
            $steps[] = 'Restore department Home Care';
            if (! $dryRun) {
                $department->restore();
            }
        } else {
            $steps[] = 'Department Home Care already active';
        }

        if (! $dryRun) {
            $department->refresh();
            if (! $department->is_active) {
                $department->update(['is_active' => true]);
            }
        }

        $pestControl = Category::withTrashed()
            ->where('department_id', $department->id)
            ->where('slug', self::PEST_CONTROL_SLUG)
            ->whereNull('parent_id')
            ->first();

        if ($pestControl === null) {
            throw new RuntimeException(
                'Root Pest Control category (slug=pest-control, parent_id=null) was not found under Home Care.',
            );
        }

        if ($pestControl->trashed()) {
            $steps[] = 'Restore root category Pest Control';
            if (! $dryRun) {
                $pestControl->restore();
            }
        } else {
            $steps[] = 'Root category Pest Control already active';
        }

        if (! $dryRun) {
            $pestControl->refresh();
            $pestControl->update([
                'department_id' => $department->id,
                'parent_id' => null,
                'origin' => CatalogOrigin::China,
                'store_id' => null,
                'name' => 'Pest Control',
                'is_active' => true,
                'sort_order' => 10,
            ]);
        }

        $duplicateChild = Category::withTrashed()
            ->where('department_id', $department->id)
            ->where('slug', self::DUPLICATE_CHILD_SLUG)
            ->first();

        $productType = CatalogProductType::withTrashed()
            ->where(function ($query) use ($duplicateChild, $pestControl) {
                $query->where('slug', self::PRODUCT_TYPE_LEGACY_SLUG)
                    ->orWhere('slug', self::PRODUCT_TYPE_SLUG)
                    ->orWhere('name', 'Insectcide Spray')
                    ->orWhere('name', self::PRODUCT_TYPE_NAME);

                if ($duplicateChild !== null) {
                    $query->orWhere('subcategory_id', $duplicateChild->id);
                }

                $query->orWhere('subcategory_id', $pestControl->id);
            })
            ->orderByRaw(
                'CASE slug WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END',
                [self::PRODUCT_TYPE_LEGACY_SLUG, self::PRODUCT_TYPE_SLUG],
            )
            ->first();

        if ($productType === null) {
            throw new RuntimeException(
                'Insecticide/Insectcide Spray product type was not found under Home Care Pest Control.',
            );
        }

        if ($productType->trashed()) {
            $steps[] = 'Restore product type Insecticide Spray';
            if (! $dryRun) {
                $productType->restore();
            }
        } else {
            $steps[] = 'Product type already present (will normalize name/slug/category)';
        }

        $steps[] = 'Reassign product type to root Pest Control and rename to Insecticide Spray';
        if (! $dryRun) {
            $productType->refresh();
            $productType->update([
                'name' => self::PRODUCT_TYPE_NAME,
                'slug' => $this->ensureUniqueProductTypeSlug(self::PRODUCT_TYPE_SLUG, $productType->id),
                'subcategory_id' => $pestControl->id,
                'is_active' => true,
            ]);
        }

        $removedDuplicateCategoryId = $duplicateChild?->id;

        if ($duplicateChild !== null) {
            $remainingTypes = CatalogProductType::withTrashed()
                ->where('subcategory_id', $duplicateChild->id)
                ->when(! $dryRun, fn ($q) => $q->where('id', '!=', $productType->id))
                ->count();

            // On execute, product type was already moved; remaining should be 0.
            // On dry-run, expect the one product type still attached until execute.
            if (! $dryRun && $remainingTypes > 0) {
                throw new RuntimeException(
                    "Cannot remove duplicate category {$duplicateChild->slug}; {$remainingTypes} product type(s) still attached.",
                );
            }

            if ($duplicateChild->trashed()) {
                $steps[] = 'Duplicate child Pest Control already soft-deleted (kept removed)';
            } else {
                $steps[] = 'Soft-delete duplicate child category pest-control-pest-control';
                if (! $dryRun) {
                    $duplicateChild->delete();
                }
            }
        } else {
            $steps[] = 'Duplicate child category pest-control-pest-control not found (already removed)';
        }

        $flavour = CatalogAttribute::withTrashed()
            ->where('slug', self::FLAVOUR_ATTRIBUTE_SLUG)
            ->first();
        $volume = CatalogAttribute::withTrashed()
            ->where('slug', self::VOLUME_ATTRIBUTE_SLUG)
            ->first();

        if ($flavour !== null) {
            $hasFlavour = DB::table('catalog_product_type_attributes')
                ->where('catalog_product_type_id', $productType->id)
                ->where('catalog_attribute_id', $flavour->id)
                ->exists();

            if ($hasFlavour) {
                $steps[] = 'Detach incorrect Flavour attribute mapping';
                $removedAttributeSlugs[] = self::FLAVOUR_ATTRIBUTE_SLUG;
                if (! $dryRun) {
                    $productType->attributes()->detach($flavour->id);
                }
            } else {
                $steps[] = 'Flavour mapping already absent';
            }
        } else {
            $steps[] = 'Flavour attribute not present in catalog (nothing to detach)';
        }

        if ($volume !== null) {
            $hasVolume = DB::table('catalog_product_type_attributes')
                ->where('catalog_product_type_id', $productType->id)
                ->where('catalog_attribute_id', $volume->id)
                ->exists();

            if ($hasVolume) {
                $steps[] = 'Keep Volume attribute mapping';
            } else {
                $steps[] = 'Attach Volume attribute mapping (was missing)';
                if (! $dryRun) {
                    $productType->attributes()->syncWithoutDetaching([
                        $volume->id => [
                            'is_required' => false,
                            'sort_order' => 1,
                        ],
                    ]);
                }
            }
        } else {
            $steps[] = 'Volume attribute not present in catalog (left unchanged)';
        }

        foreach (self::siblingCategoryDefinitions() as $definition) {
            if ($definition['slug'] === self::PEST_CONTROL_SLUG) {
                continue;
            }

            $existing = Category::withTrashed()
                ->where('department_id', $department->id)
                ->where('slug', $definition['slug'])
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $steps[] = "Restore category {$definition['name']}";
                    if (! $dryRun) {
                        $existing->restore();
                    }
                } else {
                    $steps[] = "Category {$definition['name']} already exists";
                }

                if (! $dryRun) {
                    $existing->refresh();
                    $existing->update([
                        'department_id' => $department->id,
                        'parent_id' => null,
                        'origin' => CatalogOrigin::China,
                        'store_id' => null,
                        'name' => $definition['name'],
                        'is_active' => true,
                        'sort_order' => $definition['sort_order'],
                    ]);
                }

                continue;
            }

            $steps[] = "Create category {$definition['name']}";
            $createdCategorySlugs[] = $definition['slug'];

            if (! $dryRun) {
                Category::query()->create([
                    'department_id' => $department->id,
                    'store_id' => null,
                    'parent_id' => null,
                    'origin' => CatalogOrigin::China,
                    'name' => $definition['name'],
                    'slug' => $this->ensureUniqueCategorySlug($definition['slug']),
                    'description' => null,
                    'image' => null,
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]);
            }
        }

        return [
            'dry_run' => $dryRun,
            'steps' => $steps,
            'department_id' => $department->id,
            'pest_control_id' => $pestControl->id,
            'product_type_id' => $productType->id,
            'removed_duplicate_category_id' => $removedDuplicateCategoryId,
            'created_category_slugs' => $createdCategorySlugs,
            'removed_attribute_slugs' => $removedAttributeSlugs,
        ];
    }

    private function ensureUniqueCategorySlug(string $slug): string
    {
        $original = $slug !== '' ? $slug : 'category';
        $candidate = $original;
        $counter = 1;

        while (
            Category::withTrashed()
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function ensureUniqueProductTypeSlug(string $slug, string $ignoreId): string
    {
        $original = $slug !== '' ? $slug : 'product-type';
        $candidate = $original;
        $counter = 1;

        while (
            CatalogProductType::withTrashed()
                ->where('slug', $candidate)
                ->where('id', '!=', $ignoreId)
                ->exists()
        ) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
