<?php

namespace App\Actions\AdminVariantPrices;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Http\Requests\Admin\UpdateVariantPriceRequest;
use App\Http\Resources\VariantPriceResource;
use App\Models\VariantPrice;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;

class UpdateVariantPriceAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateVariantPriceRequest $request, VariantPrice $price): array
    {
        $price->load(['variant.product']);
        $product = $this->productFromVariant($price->variant);
        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        $data = $request->validated();

        if (array_key_exists('currency', $data) && is_string($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $fresh = DB::transaction(function () use ($price, $data, $product, $hadSellableVariants) {
            $price->fill($data);
            $price->save();

            $this->assertActiveProductIntegrityAfterMutation(
                $this->purchasabilityPolicy,
                $product,
                $hadSellableVariants,
            );

            return $price->fresh();
        });

        return (new VariantPriceResource($fresh))->resolve();
    }
}
