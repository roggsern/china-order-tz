<?php

namespace App\Actions\AdminCategories;

use App\Models\Category;

class ShowCategoryAction
{
    public function handle(Category $category): Category
    {
        return $category->loadMissing(['department', 'productType', 'parent', 'children']);
    }
}
