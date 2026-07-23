<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Models\CatalogProductType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminCatalogProductTypesAction
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

        $query = CatalogProductType::query()
            ->with([
                'subcategory:id,name,slug,parent_id,department_id',
                'subcategory.parent:id,name,slug,department_id',
                'subcategory.parent.department:id,name,slug,icon',
                'subcategory.department:id,name,slug,icon',
            ])
            ->withCount(['products', 'attributes']);

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
        $subcategoryId = request()->query('subcategory_id');
        if (is_string($subcategoryId) && $subcategoryId !== '') {
            $query->where('subcategory_id', $subcategoryId);
        }

        $categoryId = request()->query('category_id');
        if (is_string($categoryId) && $categoryId !== '') {
            $query->whereHas('subcategory', function (Builder $sub) use ($categoryId) {
                $sub->where('id', $categoryId)
                    ->orWhere('parent_id', $categoryId);
            });
        }

        $departmentId = request()->query('department_id');
        if (is_string($departmentId) && $departmentId !== '') {
            $query->whereHas('subcategory', function (Builder $sub) use ($departmentId) {
                $sub->where('department_id', $departmentId)
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
