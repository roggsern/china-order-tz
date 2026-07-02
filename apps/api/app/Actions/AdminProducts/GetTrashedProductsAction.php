<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetTrashedProductsAction
{
    public function handle(): LengthAwarePaginator
    {
        return Product::onlyTrashed()
            ->with(['category', 'brand', 'inventory'])
            ->latest('deleted_at')
            ->paginate(15);
    }
}
