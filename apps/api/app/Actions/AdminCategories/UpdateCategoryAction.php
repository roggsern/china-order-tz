<?php

namespace App\Actions\AdminCategories;

use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Support\Str;

class UpdateCategoryAction
{
    public function handle(UpdateCategoryRequest $request, Category $category): Category
    {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'department_id' => $validated['department_id'],
        ];

        if (array_key_exists('slug', $validated) && is_string($validated['slug']) && trim($validated['slug']) !== '') {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($validated['slug']), $category->id);
        } elseif ($validated['name'] !== $category->name) {
            $data['slug'] = $this->generateUniqueSlug($validated['name'], $category->id);
        }

        if (array_key_exists('parent_id', $validated)) {
            $data['parent_id'] = $validated['parent_id'];
        }

        if (array_key_exists('origin', $validated)) {
            $data['origin'] = $validated['origin'];
        } elseif (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $parent = Category::query()->find($validated['parent_id']);
            if ($parent !== null && $category->origin === null) {
                $data['origin'] = $parent->resolvedOrigin()?->value;
            }
        }

        if (array_key_exists('product_type_id', $validated)) {
            $data['product_type_id'] = $validated['product_type_id'];
        }

        if (array_key_exists('image', $validated)) {
            $data['image'] = $validated['image'];
        }

        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $data['sort_order'] = $validated['sort_order'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['is_active'] = $validated['is_active'];
        }

        $category->update($data);

        return $category->fresh(['department', 'productType', 'parent', 'children']);
    }

    private function generateUniqueSlug(string $name, string $ignoreCategoryId): string
    {
        $slug = Str::slug($name);
        $original = $slug !== '' ? $slug : 'category';

        return $this->ensureUniqueSlug($original, $ignoreCategoryId);
    }

    private function ensureUniqueSlug(string $original, string $ignoreCategoryId): string
    {
        $slug = $original !== '' ? $original : 'category';
        $candidate = $slug;
        $counter = 1;

        while (
            Category::query()
                ->where('slug', $candidate)
                ->where('id', '!=', $ignoreCategoryId)
                ->exists()
        ) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
