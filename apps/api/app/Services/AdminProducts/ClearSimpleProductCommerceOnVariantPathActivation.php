<?php

namespace App\Services\AdminProducts;

use App\Models\Admin;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;

/**
 * When a product gains its first sellable variant, clear simple product pricing/inventory
 * so the variant path remains the active commerce path (ADR 053).
 */
final class ClearSimpleProductCommerceOnVariantPathActivation
{
    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly AdminInventoryApplicationService $adminInventory,
    ) {}

    public function handle(Product $product, bool $hadSellableVariantsBefore, ?Admin $actor = null): void
    {
        if ($hadSellableVariantsBefore) {
            return;
        }

        $product->loadMissing(['variants.prices', 'variants.inventories', 'inventory']);

        if (! $this->purchasabilityPolicy->hasSellableVariants($product)) {
            return;
        }

        $updates = [];

        if ((float) $product->price > 0) {
            $updates['price'] = 0;
        }

        if ($product->cost_price !== null && (float) $product->cost_price > 0) {
            $updates['cost_price'] = null;
        }

        if ($product->compare_at_price !== null && (float) $product->compare_at_price > 0) {
            $updates['compare_at_price'] = null;
        }

        if ($updates !== []) {
            $product->update($updates);
        }

        $hasSimpleInventory = Inventory::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->exists();

        if ($hasSimpleInventory) {
            $this->adminInventory->setSimpleProductStock(
                product: $product->fresh() ?? $product,
                targetQuantity: 0,
                actor: $actor,
                reason: 'Variant path activated — clear simple product stock',
            );
        }
    }
}
