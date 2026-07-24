<?php

namespace App\Actions\AdminVariantInventories;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Http\Requests\Admin\UpdateVariantInventoryRequest;
use App\Http\Resources\VariantInventoryResource;
use App\Models\Admin;
use App\Models\VariantInventory;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateVariantInventoryAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateVariantInventoryRequest $request, VariantInventory $inventory): array
    {
        $inventory->load(['variant.product']);
        $product = $this->productFromVariant($inventory->variant);
        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        $updated = DB::transaction(function () use ($inventory, $request, $admin, $product, $hadSellableVariants) {
            $row = $this->adminInventory->updateVariantInventory(
                $inventory,
                $request->validated(),
                $admin,
            );

            $this->assertActiveProductIntegrityAfterMutation(
                $this->purchasabilityPolicy,
                $product,
                $hadSellableVariants,
            );

            return $row;
        });

        return (new VariantInventoryResource($updated))->resolve();
    }
}
