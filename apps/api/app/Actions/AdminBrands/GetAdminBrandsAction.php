<?php

namespace App\Actions\AdminBrands;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminBrandsAction
{
    public function handle(): LengthAwarePaginator
    {
        return Brand::query()
            ->latest()
            ->paginate(15);
    }
}
