<?php

namespace App\Actions\AdminStores;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Store;
use App\Support\Catalog\CatalogLeafCategoryRules;
use App\Support\Catalog\TzTaxonomyImportIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TEMPLATE/COPY China taxonomy into a TZ_LOCAL store catalog.
 *
 * - Creates store-owned categories (origin=tz, store_id, department_id=null)
 * - Optionally clones CPT rows under target leaves (new subcategory_id)
 * - Optionally snapshots CPT↔attribute pivots using shared global attribute IDs
 * - Idempotent via deterministic store-scoped slugs (no mapping table)
 */
class ImportTaxonomyToStoreAction
{
    /**
     * @param  array{
     *     department_id: string,
     *     category_ids: list<string>,
     *     include_product_types?: bool,
     *     include_attribute_mappings?: bool
     * }  $input
     * @return array<string, mixed>
     */
    public function handle(Store $store, array $input): array
    {
        $departmentId = (string) $input['department_id'];
        $selectedIds = array_values(array_unique(array_filter(
            $input['category_ids'] ?? [],
            fn ($id) => is_string($id) && $id !== '',
        )));
        $includeProductTypes = (bool) ($input['include_product_types'] ?? true);
        $includeAttributeMappings = (bool) ($input['include_attribute_mappings'] ?? true);

        if ($includeAttributeMappings && ! $includeProductTypes) {
            throw ValidationException::withMessages([
                'include_attribute_mappings' => [
                    'Attribute mappings require Include Product Types.',
                ],
            ]);
        }

        if ($selectedIds === []) {
            throw ValidationException::withMessages([
                'category_ids' => ['Select at least one category to import.'],
            ]);
        }

        return DB::transaction(function () use (
            $store,
            $departmentId,
            $selectedIds,
            $includeProductTypes,
            $includeAttributeMappings,
        ) {
            $sourceById = $this->loadAndValidateSources($departmentId, $selectedIds);
            $expanded = $this->expandWithAncestors($sourceById, $selectedIds);
            $ordered = $this->orderParentsBeforeChildren($expanded);

            $sourceToTarget = [];
            $categoriesCreated = 0;
            $categoriesReused = 0;
            $importedCategories = [];

            foreach ($ordered as $source) {
                $result = $this->provisionCategory($store, $source, $sourceToTarget);
                $sourceToTarget[$source->id] = $result['category'];
                if ($result['created']) {
                    $categoriesCreated++;
                } else {
                    $categoriesReused++;
                }
                $importedCategories[] = $result['category'];
            }

            $productTypesCreated = 0;
            $productTypesReused = 0;
            $attributeMappingsSynced = 0;
            $importedProductTypes = [];

            if ($includeProductTypes) {
                foreach ($ordered as $source) {
                    $target = $sourceToTarget[$source->id] ?? null;
                    if (! $target instanceof Category) {
                        continue;
                    }

                    // CPT may only attach to leaf categories in the target tree.
                    if (! CatalogLeafCategoryRules::isLeaf($target)) {
                        continue;
                    }

                    $sourceTypes = CatalogProductType::query()
                        ->where('subcategory_id', $source->id)
                        ->where('is_active', true)
                        ->with(['attributes' => fn ($q) => $q->orderByPivot('sort_order')])
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get();

                    foreach ($sourceTypes as $sourceType) {
                        $cptResult = $this->provisionProductType(
                            $target,
                            $sourceType,
                            $includeAttributeMappings,
                        );
                        if ($cptResult['created']) {
                            $productTypesCreated++;
                        } else {
                            $productTypesReused++;
                        }
                        $attributeMappingsSynced += $cptResult['attribute_mappings_synced'];
                        $importedProductTypes[] = $cptResult['product_type'];
                    }
                }
            }

            return [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                ],
                'categories_created' => $categoriesCreated,
                'categories_reused' => $categoriesReused,
                'product_types_created' => $productTypesCreated,
                'product_types_reused' => $productTypesReused,
                'attribute_mappings_synced' => $attributeMappingsSynced,
                'categories' => collect($importedCategories)->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'parent_id' => $c->parent_id,
                    'store_id' => $c->store_id,
                    'origin' => $c->origin instanceof CatalogOrigin ? $c->origin->value : (string) $c->origin,
                    'department_id' => $c->department_id,
                ])->values()->all(),
                'product_types' => collect($importedProductTypes)->map(fn (CatalogProductType $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'subcategory_id' => $t->subcategory_id,
                ])->values()->all(),
            ];
        });
    }

    /**
     * @param  list<string>  $selectedIds
     * @return Collection<string, Category>
     */
    private function loadAndValidateSources(string $departmentId, array $selectedIds): Collection
    {
        /** @var Collection<int, Category> $found */
        $found = Category::query()
            ->whereIn('id', $selectedIds)
            ->get();

        $byId = $found->keyBy('id');

        foreach ($selectedIds as $id) {
            $category = $byId->get($id);
            if ($category === null) {
                if (Category::onlyTrashed()->whereKey($id)->exists()) {
                    throw ValidationException::withMessages([
                        'category_ids' => ['One or more selected categories have been deleted.'],
                    ]);
                }

                throw ValidationException::withMessages([
                    'category_ids' => ['One or more selected categories are invalid.'],
                ]);
            }

            $origin = $category->origin instanceof CatalogOrigin
                ? $category->origin->value
                : (string) $category->origin;

            if ($origin !== CatalogOrigin::China->value) {
                throw ValidationException::withMessages([
                    'category_ids' => ['Only China catalog categories can be imported.'],
                ]);
            }

            if ((string) $category->department_id !== $departmentId) {
                throw ValidationException::withMessages([
                    'category_ids' => ['Selected categories must belong to the chosen department.'],
                ]);
            }

            if (! $category->is_active) {
                throw ValidationException::withMessages([
                    'category_ids' => ['Inactive China categories cannot be imported.'],
                ]);
            }
        }

        return $byId;
    }

    /**
     * @param  Collection<string, Category>  $selectedById
     * @param  list<string>  $selectedIds
     * @return Collection<string, Category>
     */
    private function expandWithAncestors(Collection $selectedById, array $selectedIds): Collection
    {
        $expanded = collect();
        $departmentId = (string) $selectedById->first()->department_id;

        /** @var Collection<string, Category> $departmentTree */
        $departmentTree = Category::query()
            ->where('origin', CatalogOrigin::China)
            ->where('department_id', $departmentId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        foreach ($selectedIds as $id) {
            $current = $departmentTree->get($id) ?? $selectedById->get($id);
            $guard = 0;
            while ($current !== null && $guard++ < 100) {
                if (! $current->is_active) {
                    throw ValidationException::withMessages([
                        'category_ids' => [
                            "Required parent “{$current->name}” is inactive and cannot be provisioned.",
                        ],
                    ]);
                }

                $expanded->put($current->id, $current);
                $parentId = $current->parent_id;
                $current = $parentId ? $departmentTree->get($parentId) : null;
            }
        }

        return $expanded;
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @return list<Category>
     */
    private function orderParentsBeforeChildren(Collection $categories): array
    {
        $remaining = $categories->keyBy('id');
        $ordered = [];
        $placed = [];

        while ($remaining->isNotEmpty()) {
            $progress = false;

            foreach ($remaining as $id => $category) {
                $parentId = $category->parent_id;
                $parentReady = $parentId === null
                    || isset($placed[$parentId])
                    || ! $categories->has($parentId);

                if (! $parentReady) {
                    continue;
                }

                $ordered[] = $category;
                $placed[$id] = true;
                $remaining->forget($id);
                $progress = true;
            }

            if (! $progress) {
                // Cycle or missing parent — append remaining in stable order.
                foreach ($remaining->sortBy('name') as $category) {
                    $ordered[] = $category;
                }
                break;
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, Category>  $sourceToTarget
     * @return array{category: Category, created: bool}
     */
    private function provisionCategory(Store $store, Category $source, array $sourceToTarget): array
    {
        $targetSlug = TzTaxonomyImportIdentity::categorySlug(
            (string) $store->slug,
            (string) $source->slug,
        );

        $parentId = null;
        if ($source->parent_id !== null) {
            $parentTarget = $sourceToTarget[$source->parent_id] ?? null;
            if ($parentTarget instanceof Category) {
                $parentId = $parentTarget->id;
            }
        }

        $existing = Category::query()
            ->where('store_id', $store->id)
            ->where('origin', CatalogOrigin::Tz)
            ->where('slug', $targetSlug)
            ->first();

        if ($existing !== null) {
            // Reuse unambiguously matched store category. Do not merge by display name.
            // Keep operator-controlled is_active; repair parent_id / ownership if needed.
            $existing->fill([
                'name' => $source->name,
                'parent_id' => $parentId,
                'department_id' => null,
                'origin' => CatalogOrigin::Tz,
                'store_id' => $store->id,
                'sort_order' => $source->sort_order,
                'description' => $existing->description ?: $source->description,
                'image' => $existing->image ?: $source->image,
            ]);
            $existing->save();

            return ['category' => $existing->fresh(), 'created' => false];
        }

        // Guard: slug globally unique — if taken outside this store, fail loudly.
        if (Category::withTrashed()->where('slug', $targetSlug)->exists()) {
            throw ValidationException::withMessages([
                'category_ids' => [
                    "Cannot import “{$source->name}”: slug “{$targetSlug}” is already in use outside this store catalog.",
                ],
            ]);
        }

        $created = Category::query()->create([
            'department_id' => null,
            'store_id' => $store->id,
            'parent_id' => $parentId,
            'origin' => CatalogOrigin::Tz,
            'name' => $source->name,
            'slug' => $targetSlug,
            'description' => $source->description,
            'image' => $source->image,
            'sort_order' => $source->sort_order,
            'is_active' => true,
        ]);

        return ['category' => $created->fresh(), 'created' => true];
    }

    /**
     * @return array{product_type: CatalogProductType, created: bool, attribute_mappings_synced: int}
     */
    private function provisionProductType(
        Category $targetLeaf,
        CatalogProductType $sourceType,
        bool $includeAttributeMappings,
    ): array {
        $targetSlug = TzTaxonomyImportIdentity::productTypeSlug(
            (string) $targetLeaf->slug,
            (string) $sourceType->name,
        );

        $existing = CatalogProductType::query()->where('slug', $targetSlug)->first();

        if ($existing !== null) {
            // Idempotent reuse only when the CPT already points at this store leaf
            // (or is unbound to another leaf of a different identity).
            if ((string) $existing->subcategory_id !== (string) $targetLeaf->id) {
                $existingLeaf = Category::query()->find($existing->subcategory_id);
                $sameStore = $existingLeaf
                    && (string) $existingLeaf->store_id === (string) $targetLeaf->store_id
                    && $existingLeaf->origin === CatalogOrigin::Tz;

                if (! $sameStore) {
                    throw ValidationException::withMessages([
                        'include_product_types' => [
                            "Cannot provision product type “{$sourceType->name}”: slug “{$targetSlug}” belongs to another catalog.",
                        ],
                    ]);
                }

                // Same store but different leaf — retarget to the imported leaf.
                $existing->subcategory_id = $targetLeaf->id;
            }

            $existing->fill([
                'name' => $sourceType->name,
                'description' => $existing->description ?: $sourceType->description,
                'image' => $existing->image ?: $sourceType->image,
                'sort_order' => $sourceType->sort_order,
                'is_active' => true,
            ]);
            $existing->save();

            $synced = 0;
            if ($includeAttributeMappings) {
                $synced = $this->snapshotAttributeMappings($sourceType, $existing);
            }

            return [
                'product_type' => $existing->fresh(),
                'created' => false,
                'attribute_mappings_synced' => $synced,
            ];
        }

        if (CatalogProductType::withTrashed()->where('slug', $targetSlug)->exists()) {
            throw ValidationException::withMessages([
                'include_product_types' => [
                    "Cannot provision product type “{$sourceType->name}”: slug “{$targetSlug}” exists in trash.",
                ],
            ]);
        }

        $created = CatalogProductType::query()->create([
            'subcategory_id' => $targetLeaf->id,
            'name' => $sourceType->name,
            'slug' => $targetSlug,
            'image' => $sourceType->image,
            'description' => $sourceType->description,
            'sort_order' => $sourceType->sort_order,
            'is_active' => true,
        ]);

        $synced = 0;
        if ($includeAttributeMappings) {
            $synced = $this->snapshotAttributeMappings($sourceType, $created);
        }

        return [
            'product_type' => $created->fresh(),
            'created' => true,
            'attribute_mappings_synced' => $synced,
        ];
    }

    private function snapshotAttributeMappings(
        CatalogProductType $sourceType,
        CatalogProductType $targetType,
    ): int {
        $sourceType->loadMissing('attributes');

        $sync = [];
        foreach ($sourceType->attributes as $index => $attribute) {
            $sync[$attribute->id] = [
                'is_required' => (bool) ($attribute->pivot->is_required ?? false),
                'sort_order' => (int) ($attribute->pivot->sort_order ?? ($index + 1)),
            ];
        }

        // Snapshot: replace target mappings with source snapshot (idempotent).
        $targetType->attributes()->sync($sync);

        return count($sync);
    }
}
