<?php

namespace App\Actions\AdminProducts;

use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

class SetPrimaryProductImageAction
{
    public function handle(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);
        });
    }
}
