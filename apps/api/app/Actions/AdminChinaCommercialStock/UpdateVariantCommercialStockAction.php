<?php

namespace App\Actions\AdminChinaCommercialStock;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Http\Requests\Admin\UpdateVariantCommercialStockRequest;
use App\Http\Resources\ChinaCommercialStockResource;
use App\Models\ProductVariant;
use App\Services\AdminProducts\ClearSimpleProductCommerceOnVariantPathActivation;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateVariantCommercialStockAction
{
    use GuardsActiveProductSubResourceIntegrity;

    public function __construct(
        private readonly ChinaCommercialStockService $commercialStock,
        private readonly CommerceChannelResolver $commerceChannels,
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly ClearSimpleProductCommerceOnVariantPathActivation $simpleCommerceCleaner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateVariantCommercialStockRequest $request, ProductVariant $variant): array
    {
        $variant->loadMissing(['product.commerceChannel']);
        $product = $this->productFromVariant($variant);
        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        $this->assertChinaImportProduct($product);

        $availableQuantity = (int) $request->validated('available_quantity');

        $row = DB::transaction(function () use ($product, $variant, $availableQuantity, $hadSellableVariants) {
            $updated = $this->commercialStock->setAvailable($product, $availableQuantity, $variant);

            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );

            return $updated;
        });

        return (new ChinaCommercialStockResource($row))->resolve();
    }

    private function assertChinaImportProduct(\App\Models\Product $product): void
    {
        if ($this->commerceChannels->isChinaImportProduct($product)) {
            return;
        }

        throw ValidationException::withMessages([
            'commerce_channel' => ['Commercial stock is only available for CHINA_IMPORT products.'],
        ]);
    }
}
