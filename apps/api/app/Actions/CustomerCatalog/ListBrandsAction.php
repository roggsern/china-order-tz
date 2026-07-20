<?php

namespace App\Actions\CustomerCatalog;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ListBrandsAction
{
    /**
     * @return Collection<int, Brand>
     */
    public function handle(): Collection
    {
        $query = Brand::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name');

        $categoryId = request()->query('category_id');

        // When Catalog Bible mappings exist, filter by category (and ancestors).
        // Until then, brands remain independently listable.
        if (is_string($categoryId) && $categoryId !== '') {
            $categoryIdsToCheck = $this->categoryAndAncestorIds($categoryId);

            $hasLinks = DB::table('brand_category')
                ->whereIn('category_id', $categoryIdsToCheck)
                ->exists();

            if ($hasLinks) {
                $query->whereHas(
                    'categories',
                    fn (Builder $q) => $q->whereIn('categories.id', $categoryIdsToCheck),
                );
            }
        }

        if (request()->boolean('with_products', false) && ! (is_string($categoryId) && $categoryId !== '')) {
            $query->whereHas('products', fn (Builder $q) => $q->active());
        }

        return $query->get([
            'id',
            'name',
            'slug',
            'logo',
            'banner',
            'country',
            'is_featured',
            'sort_order',
        ]);
    }

    /**
     * @return list<string>
     */
    private function categoryAndAncestorIds(string $categoryId): array
    {
        $ids = [];
        $current = Category::query()->find($categoryId);

        while ($current !== null) {
            $ids[] = $current->id;
            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return $ids !== [] ? $ids : [$categoryId];
    }
}
