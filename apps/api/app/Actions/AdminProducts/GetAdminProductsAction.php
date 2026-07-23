<?php

namespace App\Actions\AdminProducts;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminProductsAction
{
    public function handle(): LengthAwarePaginator
    {
        $search = trim((string) request()->query('search', ''));
        $category = request()->query('category_id') ?? request()->query('category');
        $subcategory = request()->query('subcategory_id');
        $department = request()->query('department_id');
        $brand = request()->query('brand_id') ?? request()->query('brand');
        $catalogProductType = request()->query('catalog_product_type_id');
        $status = request()->query('status');
        $sort = request()->query('sort');
        $direction = strtolower((string) request()->query('direction', 'desc'));
        $perPage = (int) request()->query('per_page', 15);

        if ($perPage < 1) {
            $perPage = 15;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $allowedSorts = ['name', 'price', 'created_at', 'sort_order'];
        $allowedDirections = ['asc', 'desc'];

        if (! in_array($direction, $allowedDirections, true)) {
            $direction = 'desc';
        }

        $sortField = in_array($sort, $allowedSorts, true) ? $sort : null;

        $query = Product::query()
            ->with([
                'commerceChannel',
                'category.department',
                'category.parent',
                'brand',
                'catalogProductType.subcategory',
                'inventory',
                'images',
                'priceTiers',
                'variants.attributeValues',
                'variants.inventory',
                'variants.inventories',
                'variants.priceTiers',
            ]);

        if (request()->boolean('trashed')) {
            $query->onlyTrashed();
        }

        return $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($term) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(short_description) LIKE ?', [$term]);
                });
            })
            ->when(filled($category), function (Builder $query) use ($category) {
                $categoryIds = $this->categoryAndDescendantIds((string) $category);

                $query->where(function (Builder $query) use ($category, $categoryIds) {
                    $query->whereIn('category_id', $categoryIds)
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('slug', $category));
                });
            })
            ->when(filled($subcategory), function (Builder $query) use ($subcategory) {
                $query->where('category_id', $subcategory);
            })
            ->when(filled($department), function (Builder $query) use ($department) {
                $query->whereHas(
                    'category',
                    fn (Builder $categoryQuery) => $categoryQuery->where('department_id', $department),
                );
            })
            ->when(filled($brand), function (Builder $query) use ($brand) {
                $query->where(function (Builder $query) use ($brand) {
                    $query->where('brand_id', $brand)
                        ->orWhereHas('brand', fn (Builder $brandQuery) => $brandQuery->where('slug', $brand));
                });
            })
            ->when(filled($catalogProductType), function (Builder $query) use ($catalogProductType) {
                $query->where('catalog_product_type_id', $catalogProductType);
            })
            ->when(in_array($status, ['0', '1'], true), fn (Builder $query) => $query->where('is_active', $status === '1'))
            ->when(
                in_array($status, ['draft', 'active', 'out_of_stock', 'archived'], true),
                fn (Builder $query) => $query->where('lifecycle_status', $status),
            )
            ->when($this->booleanQuery('featured') === true || $this->booleanQuery('is_featured') === true, fn (Builder $query) => $query->where('is_featured', true))
            ->when($this->booleanQuery('featured') === false || $this->booleanQuery('is_featured') === false, fn (Builder $query) => $query->where('is_featured', false))
            ->when(
                request()->query('is_demo') === '1',
                fn (Builder $query) => $query->where('is_demo', true),
            )
            ->when(
                request()->query('is_demo') === '0',
                fn (Builder $query) => $query->where('is_demo', false),
            )
            ->when(
                $sortField !== null,
                fn (Builder $query) => $query->orderBy($sortField, $direction),
                fn (Builder $query) => $query->orderBy('sort_order')->orderByDesc('created_at'),
            )
            ->paginate($perPage);
    }

    private function booleanQuery(string $key): ?bool
    {
        if (! request()->has($key)) {
            return null;
        }

        $raw = request()->query($key);

        if ($raw === '1' || $raw === 'true' || $raw === true) {
            return true;
        }

        if ($raw === '0' || $raw === 'false' || $raw === false) {
            return false;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function categoryAndDescendantIds(string $categoryId): array
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            $frontier = [];
            foreach ($children as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $frontier[] = $childId;
                }
            }
        }

        return $ids;
    }
}
