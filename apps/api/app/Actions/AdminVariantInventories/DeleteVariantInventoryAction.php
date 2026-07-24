<?php

namespace App\Actions\AdminVariantInventories;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Models\VariantInventory;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;

class DeleteVariantInventoryAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
    ) {}

    public function handle(VariantInventory $inventory): void
    {
        $inventory->load(['variant.product']);
        $product = $this->productFromVariant($inventory->variant);
        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($inventory, $product, $hadSellableVariants) {
            $this->adminInventory->deleteVariantInventory($inventory);

            $this->assertActiveProductIntegrityAfterMutation(
                $this->purchasabilityPolicy,
                $product,
                $hadSellableVariants,
            );
        });
    }
}
