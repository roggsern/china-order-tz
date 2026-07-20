<?php

namespace App\Actions\AdminProductTypes;

use App\Models\Category;
use App\Services\ProductConfiguration\LoadProductFormSchema;

class LoadCategoryProductFormSchemaAction
{
    public function __construct(
        private readonly LoadProductFormSchema $loadProductFormSchema,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Category $category): array
    {
        $category->loadMissing(['parent', 'productType']);

        return $this->loadProductFormSchema->forCategory($category);
    }
}
