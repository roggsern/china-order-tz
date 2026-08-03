<?php

namespace App\Actions\AdminVariantInventories;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Http\Requests\Admin\StoreVariantInventoryRequest;
use App\Http\Resources\VariantInventoryResource;
use App\Models\Admin;
use App\Models\ProductVariant;
use App\Services\AdminProducts\ClearSimpleProductCommerceOnVariantPathActivation;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateVariantInventoryAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly ClearSimpleProductCommerceOnVariantPathActivation $simpleCommerceCleaner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StoreVariantInventoryRequest $request, ProductVariant $variant): array
    {
        $variant->load(['product.variants.prices', 'product.variants.inventories', 'product.inventory']);
        $product = $this->productFromVariant($variant);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        $inventory = DB::transaction(function () use ($request, $variant, $admin, $product, $hadSellableVariants) {
            $created = $this->adminInventory->createVariantInventory(
                $variant,
                $request->validated(),
                $admin,
            );

            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );

            return $created;
        });

        return (new VariantInventoryResource($inventory))->resolve();
    }
}
