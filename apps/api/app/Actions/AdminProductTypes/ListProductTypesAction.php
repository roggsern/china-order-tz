<?php

namespace App\Actions\AdminProductTypes;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Collection;

class ListProductTypesAction
{
    /**
     * @return Collection<int, ProductType>
     */
    public function handle(): Collection
    {
        return ProductType::query()
            ->where('is_active', true)
            ->with([
                'typeAttributes.attribute.values',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
