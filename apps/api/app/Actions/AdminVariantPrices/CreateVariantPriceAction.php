<?php

namespace App\Actions\AdminVariantPrices;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Http\Requests\Admin\StoreVariantPriceRequest;
use App\Http\Resources\VariantPriceResource;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Services\AdminProducts\ClearSimpleProductCommerceOnVariantPathActivation;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;

class CreateVariantPriceAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly ClearSimpleProductCommerceOnVariantPathActivation $simpleCommerceCleaner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StoreVariantPriceRequest $request, ProductVariant $variant): array
    {
        $variant->load(['product.variants.prices', 'product.variants.inventories', 'product.inventory']);
        $product = $this->productFromVariant($variant);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        $price = DB::transaction(function () use ($request, $variant, $product, $hadSellableVariants) {
            $data = $request->validated();

            $created = VariantPrice::query()->create([
                'product_variant_id' => $variant->id,
                'price_type' => $data['price_type'],
                'currency' => strtoupper($data['currency']),
                'amount' => $data['amount'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'minimum_quantity' => (int) ($data['minimum_quantity'] ?? 1),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );

            return $created;
        });

        return (new VariantPriceResource($price))->resolve();
    }
}
