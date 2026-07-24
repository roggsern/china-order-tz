<?php

namespace App\Actions\AdminProductVariants;

use App\Actions\AdminProductVariants\Concerns\ResolvesVariantDefaults;
use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteProductVariantAction
{
    use GuardsActiveProductSubResourceIntegrity;
    use ResolvesVariantDefaults;

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
    ) {}

    public function handle(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'variant' => ['Variant does not belong to this product.'],
            ]);
        }

        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($product, $variant, $hadSellableVariants) {
            $variant->catalogAttributeValues()->delete();
            $variant->delete();
            $this->ensureSingleDefault($product);
            $this->assertActiveProductIntegrityAfterMutation(
                $this->purchasabilityPolicy,
                $product,
                $hadSellableVariants,
            );
        });
    }
}
