<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\SyncProductPriceTiersRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\SyncConfigurationPriceTiers;
use Illuminate\Validation\ValidationException;

class SyncProductPriceTiersAction
{
    public function __construct(
        private readonly SyncConfigurationPriceTiers $syncConfigurationPriceTiers,
    ) {}

    /**
     * @return list<\App\Models\ConfigurationPriceTier>
     */
    public function handle(SyncProductPriceTiersRequest $request, Product $product): array
    {
        $validated = $request->validated();
        $configurationId = $validated['configuration_id'] ?? null;
        $configuration = null;

        if ($configurationId !== null) {
            $configuration = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('id', $configurationId)
                ->first();

            if ($configuration === null) {
                throw ValidationException::withMessages([
                    'configuration_id' => ['Configuration does not belong to this product.'],
                ]);
            }
        }

        $this->syncConfigurationPriceTiers->handle(
            $product,
            $configuration,
            $validated['price_tiers'],
        );

        $query = $product->priceTiers()->orderBy('min_quantity');

        if ($configuration !== null) {
            $query->where('product_variant_id', $configuration->id);
        } else {
            $query->whereNull('product_variant_id');
        }

        return $query->get()->all();
    }
}
