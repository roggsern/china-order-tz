<?php

namespace App\Actions\AdminCategories;

use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Support\Str;

class CreateCategoryAction
{
    public function handle(StoreCategoryRequest $request): Category
    {
        $validated = $request->validated();
        $parent = isset($validated['parent_id'])
            ? Category::query()->find($validated['parent_id'])
            : null;

        $origin = $validated['origin'] ?? null;
        if ($origin === null && $parent !== null) {
            $origin = $parent->resolvedOrigin()?->value;
        }

        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $validated['name'];

        return Category::create([
            'department_id' => $validated['department_id'],
            'store_id' => $validated['store_id'] ?? null,
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
