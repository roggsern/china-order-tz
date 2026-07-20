<?php

namespace App\Actions\AdminVariantPrices;

use App\Models\VariantPrice;

class DeleteVariantPriceAction
{
    public function handle(VariantPrice $price): void
    {
        $price->delete();
    }
}
