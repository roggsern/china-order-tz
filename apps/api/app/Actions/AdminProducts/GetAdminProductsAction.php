<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminProductsAction
{
    public function handle(): LengthAwarePaginator
    {
        $search = trim((string) request()->query('search', ''));

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
            ->latest()
            ->paginate(15);
    }
}
