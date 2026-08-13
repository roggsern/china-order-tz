<?php

namespace App\Services\Search;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\CommerceChannel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared CHINA_IMPORT sellable listing constraints (shipping, stock, visibility).
 * Used by China storefront browse and unified marketplace search.
 */
class ChinaSellableProductQuery
{
    public function apply(Builder $query): Builder
    {
        $chinaChannelId = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->value('id');

        if ($chinaChannelId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->where('is_active', true)
            ->where('is_demo', false)
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->whereNull('store_id')
            // Equality beats whereHas(EXISTS) for the hot COUNT + page SELECT path.
            ->where('commerce_channel_id', $chinaChannelId)
            ->whereHas('shippingOptions', fn (Builder $q) => $q->available()->where('price', '>', 0))
            ->where(function (Builder $q) {
                $q->whereHas('variants', fn (Builder $variant) => $this->applySellableVariantConstraints($variant))
                    ->orWhere(function (Builder $simple) {
                        $simple->where('price', '>', 0)
                            ->where(function (Builder $stock) {
                                $stock->whereHas('inventory', fn (Builder $inventory) => $inventory->whereNull('product_variant_id'))
                                    ->orWhereHas('chinaCommercialStocks', function (Builder $commercial) {
                                        $commercial->whereNull('product_variant_id')
                                            ->where('available_quantity', '>', 0);
                                    });
                            })
                            ->whereDoesntHave('variants', fn (Builder $variant) => $this->applySellableVariantConstraints($variant));
                    });
            });
    }

    /**
     * Mirrors ProductPurchasabilityPolicy sellable-variant rules for listing safety.
     */
    public function applySellableVariantConstraints(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $variant) {
                $variant->where(function (Builder $priced) {
                    $priced->whereNotNull('price')->where('price', '>', 0);
                })->orWhereHas('prices', function (Builder $prices) {
                    $prices->ofType(VariantPriceType::Retail)
                        ->active()
                        ->where('amount', '>', 0);
                });
            })
            ->where(function (Builder $variant) {
                $variant->whereHas('inventories', function (Builder $inventory) {
                    $inventory->where('warehouse_code', 'MAIN')->where('is_active', true);
                })
                    ->orWhereHas('inventory')
                    ->orWhereHas('chinaCommercialStock', function (Builder $commercial) {
                        $commercial->where('available_quantity', '>', 0);
                    });
            });
    }
}
