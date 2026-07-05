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

        $data = ['name' => $validated['name']];

        if ($validated['name'] !== $category->name) {
            $data['slug'] = $this->generateUniqueSlug($validated['name'], $category->id);
        }

        $category->update($data);

        return $category->fresh();
    }

    private function generateUniqueSlug(string $name, string $ignoreCategoryId): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (
            Category::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreCategoryId)
                ->exists()
        ) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
