<?php

namespace App\Actions\AdminCategories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminCategoriesAction
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

        $query = Category::query()
            ->with(['department', 'productType', 'parent'])
            ->withCount('products');

        if (request()->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $this->applyFilters($query);

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    private function applyFilters(Builder $query): void
    {
        $departmentId = request()->query('department_id');
        if (is_string($departmentId) && $departmentId !== '') {
            $query->where('department_id', $departmentId);
        }

        $origin = request()->query('origin');
        if (is_string($origin) && $origin !== '') {
            $query->where('origin', $origin);
        }

        if (request()->query->has('parent_id')) {
            $parentId = request()->query('parent_id');
            if ($parentId === null || $parentId === '' || $parentId === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        if (request()->boolean('roots_only')) {
            $query->whereNull('parent_id');
        }

        if (request()->has('is_active')) {
            $raw = request()->query('is_active');
            if ($raw === '1' || $raw === 'true' || $raw === true) {
                $query->where('is_active', true);
            } elseif ($raw === '0' || $raw === 'false' || $raw === false) {
                $query->where('is_active', false);
            }
        }

        $search = request()->query('search');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }
    }
}
