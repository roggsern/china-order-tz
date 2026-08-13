<?php

namespace App\Services\China\Procurement;

use App\Models\ChinaCommercialStock;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Customer-facing commercial availability for CHINA_IMPORT products.
 * Does not mutate physical warehouse inventory.
 */
final class ChinaCommercialStockService
{
    public function reserveForPaidItem(OrderItem $item): ChinaCommercialStock
    {
        $item->loadMissing(['product.commerceChannel', 'variant']);

        return DB::transaction(function () use ($item): ChinaCommercialStock {
            $stock = $this->lockOrCreate(
                $item->product_id,
                $item->product_variant_id,
            );

            $qty = max(1, (int) $item->quantity);
            $available = max(0, (int) $stock->available_quantity - $qty);

            $stock->forceFill([
                'available_quantity' => $available,
                'reserved_quantity' => (int) $stock->reserved_quantity + $qty,
                'ordered_quantity' => (int) $stock->ordered_quantity + $qty,
            ])->save();

            return $stock->fresh() ?? $stock;
        });
    }

    public function releaseForCancelledItem(OrderItem $item): ?ChinaCommercialStock
    {
        $item->loadMissing(['product.commerceChannel', 'variant']);

        return DB::transaction(function () use ($item): ?ChinaCommercialStock {
            $stock = ChinaCommercialStock::query()
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                return null;
            }

            $qty = max(1, (int) $item->quantity);

            $stock->forceFill([
                'available_quantity' => (int) $stock->available_quantity + $qty,
                'reserved_quantity' => max(0, (int) $stock->reserved_quantity - $qty),
                'ordered_quantity' => max(0, (int) $stock->ordered_quantity - $qty),
            ])->save();

            return $stock->fresh() ?? $stock;
        });
    }

    public function findForProduct(Product $product, ?ProductVariant $variant = null): ?ChinaCommercialStock
    {
        // Prefer already-eager-loaded listing relations to avoid N+1 on catalog cards.
        if ($variant !== null) {
            if ($variant->relationLoaded('chinaCommercialStock')) {
                return $variant->getRelation('chinaCommercialStock');
            }

            if ($product->relationLoaded('chinaCommercialStocks')) {
                $variantId = (string) $variant->id;

                return $product->chinaCommercialStocks->first(
                    static fn (ChinaCommercialStock $row): bool => (string) $row->product_variant_id === $variantId,
                );
            }
        } elseif ($product->relationLoaded('chinaCommercialStocks')) {
            return $product->chinaCommercialStocks->first(
                static fn (ChinaCommercialStock $row): bool => $row->product_variant_id === null,
            );
        }

        return ChinaCommercialStock::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();
    }

    public function setAvailable(
        Product $product,
        int $availableQuantity,
        ?ProductVariant $variant = null,
    ): ChinaCommercialStock {
        return ChinaCommercialStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            [
                'available_quantity' => max(0, $availableQuantity),
            ],
        );
    }

    private function lockOrCreate(string $productId, ?string $variantId): ChinaCommercialStock
    {
        $stock = ChinaCommercialStock::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if ($stock !== null) {
            return $stock;
        }

        return ChinaCommercialStock::query()->create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'available_quantity' => 0,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);
    }
}
