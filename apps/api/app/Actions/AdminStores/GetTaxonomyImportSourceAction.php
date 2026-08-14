<?php

namespace App\Actions\AdminStores;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Store;
use App\Support\Catalog\TzTaxonomyImportCategoryResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Read-only China taxonomy tree for the TZ store import modal.
 *
 * Source visibility is based on Catalog Bible / department taxonomy authority,
 * NOT products and NOT Catalog Product Type existence.
 *
 * Preview annotations (NEW / REUSE EXISTING) are computed via the same
 * authoritative resolver used by ImportTaxonomyToStoreAction (persistMap=false).
 */
class GetTaxonomyImportSourceAction
{
    private TzTaxonomyImportCategoryResolver $categoryResolver;

    public function __construct(?TzTaxonomyImportCategoryResolver $categoryResolver = null)
    {
        $this->categoryResolver = $categoryResolver ?? new TzTaxonomyImportCategoryResolver;
    }

    /**
     * @return array{
     *     department: array{id: string, name: string, slug: string},
     *     categories: list<array<string, mixed>>
     * }
     */
    public function handle(Store $store, string $departmentId): array
    {
        $department = Department::query()
            ->whereKey($departmentId)
            ->where('is_active', true)
            ->first();

        if ($department === null) {
            throw ValidationException::withMessages([
                'department_id' => ['The selected department is invalid or inactive.'],
            ]);
        }

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->where('origin', CatalogOrigin::China)
            ->where('department_id', $department->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id', 'sort_order', 'is_active', 'department_id']);

        $parentIdsWithChildren = $categories
            ->pluck('parent_id')
            ->filter()
            ->unique()
            ->flip();

        // Soft-deleted already excluded by SoftDeletes. Drop truly disabled leaves
        // (inactive + no children). Keep inactive structural parents for tree display.
        $categories = $categories->filter(function (Category $category) use ($parentIdsWithChildren) {
            if ($category->is_active) {
                return true;
            }

            return $parentIdsWithChildren->has($category->id);
        })->values();

        $categoryIds = $categories->pluck('id')->all();

        /** @var Collection<string, Collection<int, CatalogProductType>> $typesByLeaf */
        $typesByLeaf = $categoryIds === []
            ? collect()
            : CatalogProductType::query()
                ->whereIn('subcategory_id', $categoryIds)
                ->where('is_active', true)
                ->withCount('attributes')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'subcategory_id', 'name', 'slug', 'is_active', 'sort_order'])
                ->groupBy('subcategory_id');

        $previewTargets = $this->previewTargets($store, $categories);

        $rows = $categories->map(function (Category $category) use ($typesByLeaf, $parentIdsWithChildren, $previewTargets) {
            $types = $typesByLeaf->get($category->id, collect());
            $isStructuralParent = $parentIdsWithChildren->has($category->id);
            $preview = $previewTargets[$category->id] ?? ['status' => 'new', 'target' => null, 'reason' => null];

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'is_structural_parent' => $isStructuralParent,
                // Selectable when active, or when inactive only as Catalog Bible parent.
                'importable' => $category->is_active || $isStructuralParent,
                'import_preview' => [
                    'status' => $preview['status'],
                    'reason' => $preview['reason'],
                    'target' => $preview['target'],
                ],
                'product_types' => $types->map(fn (CatalogProductType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'is_active' => $type->is_active,
                    'attributes_count' => (int) ($type->attributes_count ?? 0),
                    'has_attribute_mappings' => (int) ($type->attributes_count ?? 0) > 0,
                ])->values()->all(),
                'has_product_types' => $types->isNotEmpty(),
            ];
        })->values()->all();

        return [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'slug' => $department->slug,
            ],
            'categories' => $rows,
        ];
    }

    /**
     * Dry-run resolve in parent-before-child order so child preview uses reconciled parents.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, array{status: string, reason: ?string, target: ?array{id: string, name: string, slug: string}}>
     */
    private function previewTargets(Store $store, Collection $categories): array
    {
        $byId = $categories->keyBy('id');
        $ordered = $this->orderParentsBeforeChildren($byId);
        $sourceToTarget = [];
        $preview = [];

        foreach ($ordered as $source) {
            $resolved = $this->categoryResolver->resolve($store, $source, $sourceToTarget, persistMap: false);
            $target = $resolved['category'];

            if ($target instanceof Category) {
                $sourceToTarget[$source->id] = $target;
                $preview[$source->id] = [
                    'status' => 'reuse',
                    'reason' => $resolved['reason'],
                    'target' => [
                        'id' => $target->id,
                        'name' => $target->name,
                        'slug' => $target->slug,
                    ],
                ];
            } else {
                // Synthetic placeholder so children resolve parent context in preview.
                $placeholder = new Category([
                    'id' => 'preview-'.$source->id,
                    'store_id' => $store->id,
                    'origin' => CatalogOrigin::Tz,
                    'name' => $source->name,
                    'slug' => 'preview-'.$source->slug,
                    'parent_id' => null,
                ]);
                $placeholder->id = 'preview-'.$source->id;
                $sourceToTarget[$source->id] = $placeholder;
                $preview[$source->id] = [
                    'status' => 'new',
                    'reason' => null,
                    'target' => null,
                ];
            }
        }

        return $preview;
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
                foreach ($remaining->sortBy('name') as $category) {
                    $ordered[] = $category;
                }
                break;
            }
        }

        return $ordered;
    }
}
