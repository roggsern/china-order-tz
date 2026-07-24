<?php

namespace App\Actions\AdminVariantPrices;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Models\VariantPrice;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;

class DeleteVariantPriceAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
    ) {}

    public function handle(VariantPrice $price): void
    {
        $price->load(['variant.product']);
        $product = $this->productFromVariant($price->variant);
        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($price, $product, $hadSellableVariants) {
            $price->delete();

            $this->assertActiveProductIntegrityAfterMutation(
                $this->purchasabilityPolicy,
                $product,
                $hadSellableVariants,
            );
        });
    }
}
