<?php

namespace App\Actions\AdminStores;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Read-only China taxonomy tree for the TZ store import modal.
 *
 * Source visibility is based on Catalog Bible / department taxonomy authority,
 * NOT products and NOT Catalog Product Type existence.
 *
 * China intentionally keeps many parent categories inactive for storefront
 * mega-menu safety while their children remain active. Those inactive parents
 * must still appear here so admins can import the hierarchy into TZ stores.
 */
class GetTaxonomyImportSourceAction
{
    /**
     * @return array{
     *     department: array{id: string, name: string, slug: string},
     *     categories: list<array<string, mixed>>
     * }
     */
    public function handle(Store $store, string $departmentId): array
    {
        // $store is the import target — validated by the controller/route binding.
        unset($store);

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

        $rows = $categories->map(function (Category $category) use ($typesByLeaf, $parentIdsWithChildren) {
            $types = $typesByLeaf->get($category->id, collect());
            $isStructuralParent = $parentIdsWithChildren->has($category->id);

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
}
