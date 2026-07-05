<?php

namespace App\Actions\AdminCategories;

use App\Models\Category;

class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        $category->delete();
    }
}
