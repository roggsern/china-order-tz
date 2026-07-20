<?php

namespace App\Actions\CustomerCatalog;

use App\Models\Category;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowCategoryAction
{
    public function handle(string $slug): Category
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['childrenRecursive'])
            ->first();

        if ($category === null) {
            throw new NotFoundHttpException('Category not found.');
        }

        return $category;
    }
}
