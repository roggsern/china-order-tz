<?php

namespace App\Actions\AdminSubcategories;

use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RestoreSubcategoryAction
{
    public function handle(string $id): Category
    {
        $subcategory = Category::onlyTrashed()
            ->subcategories()
            ->whereKey($id)
            ->first();

        if ($subcategory === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$id]);
        }

        $subcategory->restore();

        return $subcategory
            ->fresh(['parent.department', 'department'])
            ->loadCount('products');
    }
}
