<?php

namespace App\Actions\AdminCategories;

use App\Enums\CatalogOrigin;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Support\Str;

class CreateCategoryAction
{
    public function handle(StoreCategoryRequest $request): Category
    {
        $validated = $request->validated();

        $origin = $validated['origin'] instanceof CatalogOrigin
            ? $validated['origin']->value
            : (string) $validated['origin'];

        $storeId = $origin === CatalogOrigin::China->value
            ? null
            : ($validated['store_id'] ?? null);

        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $validated['name'];

        return Category::create([
            'department_id' => $validated['department_id'],
            'store_id' => $storeId,
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($slugSource),
            'parent_id' => $validated['parent_id'] ?? null,
            'origin' => $origin,
            'product_type_id' => $validated['product_type_id'] ?? null,
            'image' => $validated['image'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ])->fresh(['department', 'productType', 'parent', 'children', 'store']);
    }

    private function generateUniqueSlug(string $value): string
    {
        $slug = Str::slug($value);
        $original = $slug !== '' ? $slug : 'category';
        $candidate = $original;
        $counter = 1;

        while (Category::where('slug', $candidate)->exists()) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
