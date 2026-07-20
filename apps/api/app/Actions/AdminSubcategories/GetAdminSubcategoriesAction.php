<?php

namespace App\Actions\AdminSubcategories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminSubcategoriesAction
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
            ->subcategories()
            ->with([
                'parent:id,name,slug,department_id',
                'parent.department:id,name,slug,icon',
                'department:id,name,slug,icon',
            ])
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
        $categoryId = request()->query('category_id');
        if (is_string($categoryId) && $categoryId !== '') {
            $query->where('parent_id', $categoryId);
        }

        $departmentId = request()->query('department_id');
        if (is_string($departmentId) && $departmentId !== '') {
            $query->where(function (Builder $inner) use ($departmentId) {
                $inner->where('department_id', $departmentId)
                    ->orWhereHas('parent', fn (Builder $parent) => $parent->where('department_id', $departmentId));
            });
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
