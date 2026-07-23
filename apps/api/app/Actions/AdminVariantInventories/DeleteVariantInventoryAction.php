<?php

namespace App\Actions\AdminVariantInventories;

use App\Models\VariantInventory;
use App\Services\Inventory\AdminInventoryApplicationService;

class DeleteVariantInventoryAction
{
    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
    ) {}

    public function handle(VariantInventory $inventory): void
    {
        $this->adminInventory->deleteVariantInventory($inventory);
    }
}
