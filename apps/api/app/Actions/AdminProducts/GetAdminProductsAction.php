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
            ->latest()
            ->paginate(15);
    }
}
