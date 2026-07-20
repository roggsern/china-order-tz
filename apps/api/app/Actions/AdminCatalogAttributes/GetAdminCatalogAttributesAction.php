<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Models\CatalogAttribute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminCatalogAttributesAction
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

        $query = CatalogAttribute::query()->with(['options', 'catalogProductTypes']);

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
        $type = request()->query('type');
        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        if (request()->has('is_filterable')) {
            $raw = request()->query('is_filterable');
            if ($raw === '1' || $raw === 'true' || $raw === true) {
                $query->where('is_filterable', true);
            } elseif ($raw === '0' || $raw === 'false' || $raw === false) {
                $query->where('is_filterable', false);
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

        $search = request()->query('search');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            });
        }
    }
}
