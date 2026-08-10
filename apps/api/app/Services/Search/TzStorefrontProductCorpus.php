<?php

namespace App\Services\Search;

use App\Enums\CommerceChannelCode;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

/**
 * TZ_LOCAL storefront-eligible products (published, visible store, stock rules).
 * Shared by TZ store browse and unified marketplace search.
 */
class TzStorefrontProductCorpus
{
    /**
     * @param  Builder<\App\Models\Product>  $query
     * @return Builder<\App\Models\Product>
     */
    public function apply(Builder $query, ?Store $store = null): Builder
    {
        $query
            ->real()
            ->published()
            ->whereNotNull('store_id')
            ->whereHas('store', fn (Builder $s) => $s->storefrontVisible())
            ->whereHas('commerceChannel', fn (Builder $q) => $q->where('code', CommerceChannelCode::TzLocal->value));

        if ($store !== null) {
            $query->where('store_id', $store->id);
            $this->applyStockVisibilityForStore($query, $store);

            return $query;
        }

        $this->applyStockVisibilityMarketplace($query);

        return $query;
    }

    /**
     * @param  Builder<\App\Models\Product>  $query
     */
    private function applyStockVisibilityForStore(Builder $query, Store $store): void
    {
        $warehouseCode = strtoupper((string) $store->code);

        $query->where(function (Builder $q) use ($warehouseCode, $store) {
            $q->whereHas('variants', function (Builder $vq) use ($warehouseCode, $store) {
                $vq->where('is_active', true)
                    ->where(function (Builder $inv) use ($warehouseCode, $store) {
                        $inv->whereDoesntHave('inventories')
                            ->orWhereHas('inventories', function (Builder $iq) use ($warehouseCode, $store) {
                                $iq->where('is_active', true)
                                    ->where(function (Builder $loc) use ($warehouseCode, $store) {
                                        $loc->where('warehouse_code', $warehouseCode)
                                            ->orWhereHas('inventoryLocation', fn (Builder $lq) => $lq
                                                ->where('store_id', $store->id)
                                                ->where('is_default', true));
                                    })
                                    ->whereRaw('(on_hand - reserved) > 0');
                            });
                    });
            })->orWhereDoesntHave('variants');
        });
    }

    /**
     * Cross-store stock visibility aligned with per-store warehouse / default location rules.
     *
     * @param  Builder<\App\Models\Product>  $query
     */
    private function applyStockVisibilityMarketplace(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereHas('variants', function (Builder $vq) {
                $vq->where('is_active', true)
                    ->where(function (Builder $inv) {
                        $inv->whereDoesntHave('inventories')
                            ->orWhereHas('inventories', function (Builder $iq) {
                                $iq->where('is_active', true)
                                    ->whereRaw('(on_hand - reserved) > 0')
                                    ->where(function (Builder $loc) {
                                        $loc->whereExists(function ($sub) {
                                            $sub->selectRaw('1')
                                                ->from('stores')
                                                ->whereColumn('stores.id', 'products.store_id')
                                                ->whereRaw('variant_inventories.warehouse_code = UPPER(stores.code)');
                                        })->orWhereHas('inventoryLocation', function (Builder $lq) {
                                            $lq->whereColumn('inventory_locations.store_id', 'products.store_id')
                                                ->where('is_default', true);
                                        });
                                    });
                            });
                    });
            })->orWhereDoesntHave('variants');
        });
    }
}
