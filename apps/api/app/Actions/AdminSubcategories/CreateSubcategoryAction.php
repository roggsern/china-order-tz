<?php

namespace App\Actions\AdminSubcategories;

use App\Http\Requests\Admin\StoreSubcategoryRequest;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSubcategoryAction
{
    public function handle(StoreSubcategoryRequest $request): Category
    {
        $validated = $request->validated();

        $parent = Category::query()->findOrFail($validated['category_id']);

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'category_id' => ['Subcategories must belong to a top-level category.'],
            ]);
        }

        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $parent->slug.'-'.$validated['name'];

        return Category::create([
            'department_id' => $parent->department_id,
            'parent_id' => $parent->id,
            'origin' => $parent->resolvedOrigin()?->value ?? $parent->origin,
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($slugSource),
            'image' => $validated['image'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ])->fresh([
            'parent.department',
            'department',
        ])->loadCount('products');
    }

    private function generateUniqueSlug(string $value): string
    {
        $slug = Str::slug($value);
        $original = $slug !== '' ? $slug : 'subcategory';
        $candidate = $original;
        $counter = 1;

        while (Category::where('slug', $candidate)->exists()) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
