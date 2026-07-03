<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminProductsAction
{
    public function handle(): LengthAwarePaginator
    {
        $search = trim((string) request()->query('search', ''));
        $category = request()->query('category');
        $brand = request()->query('brand');
        $status = request()->query('status');
        $sort = request()->query('sort');
        $direction = strtolower((string) request()->query('direction', 'desc'));

        $allowedSorts = ['name', 'price', 'created_at'];
        $allowedDirections = ['asc', 'desc'];

        if (! in_array($direction, $allowedDirections, true)) {
            $direction = 'desc';
        }

        $sortField = in_array($sort, $allowedSorts, true) ? $sort : null;

        return Product::query()
            ->with(['category', 'brand'])
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
                });
            })
            ->when(filled($category), fn ($query) => $query->where('category_id', $category))
            ->when(filled($brand), fn ($query) => $query->where('brand_id', $brand))
            ->when(in_array($status, ['0', '1'], true), fn ($query) => $query->where('is_active', $status === '1'))
            ->when(
                $sortField !== null,
                fn ($query) => $query->orderBy($sortField, $direction),
                fn ($query) => $query->latest(),
            )
            ->paginate(15);
    }
}
