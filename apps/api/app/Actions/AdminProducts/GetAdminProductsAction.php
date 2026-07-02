<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminProductsAction
{
    public function handle(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->latest()
            ->paginate(15);
    }
}
