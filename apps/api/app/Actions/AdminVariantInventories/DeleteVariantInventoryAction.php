<?php

namespace App\Actions\AdminVariantInventories;

use App\Models\VariantInventory;

class DeleteVariantInventoryAction
{
    public function handle(VariantInventory $inventory): void
    {
        $inventory->delete();
    }
}
