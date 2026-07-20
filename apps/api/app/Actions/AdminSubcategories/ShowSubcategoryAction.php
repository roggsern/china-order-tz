<?php

namespace App\Actions\AdminSubcategories;

use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShowSubcategoryAction
{
    public function handle(Category $subcategory): Category
    {
        if ($subcategory->parent_id === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$subcategory->id]);
        }

        return $subcategory
            ->loadMissing(['parent.department', 'department'])
            ->loadCount('products');
    }
}
