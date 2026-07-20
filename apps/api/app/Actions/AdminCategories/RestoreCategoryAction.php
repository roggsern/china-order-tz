<?php

namespace App\Actions\AdminCategories;

use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RestoreCategoryAction
{
    public function handle(string $id): Category
    {
        $category = Category::onlyTrashed()->whereKey($id)->first();

        if ($category === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$id]);
        }

        $category->restore();

        return $category->fresh(['department', 'productType', 'parent', 'children']);
    }
}
