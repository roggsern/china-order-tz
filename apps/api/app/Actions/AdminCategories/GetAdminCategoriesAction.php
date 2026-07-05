<?php

namespace App\Actions\AdminCategories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminCategoriesAction
{
    public function handle(): LengthAwarePaginator
    {
        return Category::query()
            ->latest()
            ->paginate(15);
    }
}
