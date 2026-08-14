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
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id', 'sort_order', 'is_active', 'department_id']);

        $categoryIds = $categories->pluck('id')->all();

        /** @var Collection<string, Collection<int, CatalogProductType>> $typesByLeaf */
        $typesByLeaf = CatalogProductType::query()
            ->whereIn('subcategory_id', $categoryIds)
            ->where('is_active', true)
            ->withCount('attributes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'subcategory_id', 'name', 'slug', 'is_active', 'sort_order'])
            ->groupBy('subcategory_id');

        $rows = $categories->map(function (Category $category) use ($typesByLeaf) {
            $types = $typesByLeaf->get($category->id, collect());

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'product_types' => $types->map(fn (CatalogProductType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'is_active' => $type->is_active,
                    'attributes_count' => (int) ($type->attributes_count ?? 0),
                    'has_attribute_mappings' => (int) ($type->attributes_count ?? 0) > 0,
                ])->values()->all(),
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
