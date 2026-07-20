<?php

namespace App\Actions\AdminSubcategories;

use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class DeleteSubcategoryAction
{
    public function handle(Category $subcategory): void
    {
        if ($subcategory->parent_id === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$subcategory->id]);
        }

        $hasChildren = Category::query()
            ->where('parent_id', $subcategory->id)
            ->exists();

        if ($hasChildren) {
            throw ValidationException::withMessages([
                'subcategory' => ['Remove nested child categories before deleting this subcategory.'],
            ]);
        }

        $subcategory->delete();
    }
}
