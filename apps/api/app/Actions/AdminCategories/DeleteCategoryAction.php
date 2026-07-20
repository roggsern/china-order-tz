<?php

namespace App\Actions\AdminCategories;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        $hasChildren = Category::query()
            ->where('parent_id', $category->id)
            ->exists();

        if ($hasChildren) {
            throw ValidationException::withMessages([
                'category' => ['Remove or reassign child categories before deleting this category.'],
            ]);
        }

        $category->delete();
    }
}
