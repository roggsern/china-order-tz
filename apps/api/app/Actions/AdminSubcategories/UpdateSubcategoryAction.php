<?php

namespace App\Actions\AdminSubcategories;

use App\Http\Requests\Admin\UpdateSubcategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateSubcategoryAction
{
    public function handle(UpdateSubcategoryRequest $request, Category $subcategory): Category
    {
        $this->ensureIsSubcategory($subcategory);

        $validated = $request->validated();
        $parent = Category::query()->findOrFail($validated['category_id']);

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'category_id' => ['Subcategories must belong to a top-level category.'],
            ]);
        }

        $data = [
            'name' => $validated['name'],
            'parent_id' => $parent->id,
            'department_id' => $parent->department_id,
        ];

        if (array_key_exists('slug', $validated) && is_string($validated['slug']) && trim($validated['slug']) !== '') {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($validated['slug']), $subcategory->id);
        } elseif ($validated['name'] !== $subcategory->name || $parent->id !== $subcategory->parent_id) {
            $data['slug'] = $this->ensureUniqueSlug(
                Str::slug($parent->slug.'-'.$validated['name']),
                $subcategory->id,
            );
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

        $subcategory->update($data);

        return $subcategory->fresh([
            'parent.department',
            'department',
        ])->loadCount('products');
    }

    private function ensureIsSubcategory(Category $subcategory): void
    {
        if ($subcategory->parent_id === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$subcategory->id]);
        }
    }

    private function ensureUniqueSlug(string $original, string $ignoreId): string
    {
        $slug = $original !== '' ? $original : 'subcategory';
        $candidate = $slug;
        $counter = 1;

        while (
            Category::query()
                ->where('slug', $candidate)
                ->where('id', '!=', $ignoreId)
                ->exists()
        ) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
