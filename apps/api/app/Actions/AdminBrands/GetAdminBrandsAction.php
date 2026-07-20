<?php

namespace App\Actions\AdminBrands;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GetAdminBrandsAction
{
    public function handle(): LengthAwarePaginator
    {
        $perPage = (int) request()->query('per_page', 15);

        if ($perPage < 1) {
            $perPage = 15;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Brand::query()
            ->with(['categories:id,name,slug,parent_id,origin'])
            ->withCount('products');

        if (request()->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $this->applyFilters($query);

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    private function applyFilters(Builder $query): void
    {
        $categoryId = request()->query('category_id');

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

        if (request()->has('is_active')) {
            $raw = request()->query('is_active');
            if ($raw === '1' || $raw === 'true' || $raw === true) {
                $query->where('is_active', true);
            } elseif ($raw === '0' || $raw === 'false' || $raw === false) {
                $query->where('is_active', false);
            }
        }

        if (request()->has('is_featured')) {
            $raw = request()->query('is_featured');
            if ($raw === '1' || $raw === 'true' || $raw === true) {
                $query->where('is_featured', true);
            } elseif ($raw === '0' || $raw === 'false' || $raw === false) {
                $query->where('is_featured', false);
            }
        }

        $search = request()->query('search');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('country', 'like', $term)
                    ->orWhere('website', 'like', $term);
            });
        }
    }

    /**
     * Walk ancestors in memory after a single categories fetch (avoids N parent queries).
     *
     * @return list<string>
     */
    private function categoryAndAncestorIds(string $categoryId): array
    {
        /** @var \Illuminate\Support\Collection<string, Category> $byId */
        $byId = Category::query()
            ->select(['id', 'parent_id'])
            ->get()
            ->keyBy('id');

        $ids = [];
        $currentId = $categoryId;
        $guard = 0;

        while ($currentId !== null && $byId->has($currentId) && $guard < 50) {
            $ids[] = $currentId;
            $parentId = $byId->get($currentId)?->parent_id;
            $currentId = is_string($parentId) && $parentId !== '' ? $parentId : null;
            $guard++;
        }

        return $ids !== [] ? $ids : [$categoryId];
    }
}
