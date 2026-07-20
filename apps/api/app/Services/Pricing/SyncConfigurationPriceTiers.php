<?php

namespace App\Services\Pricing;

use App\Enums\PriceTierType;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Syncs MOQ / quantity price tiers for a product or configuration.
 * Metadata-driven — no product-type-specific tier logic.
 */
class SyncConfigurationPriceTiers
{
    /**
     * @param  list<array{
     *     min_quantity: int,
     *     tier_type?: string,
     *     unit_price?: float|int|string|null,
     *     discount_percent?: float|int|string|null
     * }>  $tiers
     */
    public function handle(
        Product $product,
        ?ProductVariant $configuration,
        array $tiers,
    ): void {
        DB::transaction(function () use ($product, $configuration, $tiers) {
            $seenMins = [];

            foreach ($tiers as $index => $tier) {
                $minQuantity = (int) ($tier['min_quantity'] ?? 0);
                $tierType = PriceTierType::tryFromMixed($tier['tier_type'] ?? null);
                $unitPrice = $tier['unit_price'] ?? null;
                $discountPercent = $tier['discount_percent'] ?? null;

                if ($minQuantity < 1) {
                    throw ValidationException::withMessages([
                        "price_tiers.{$index}.min_quantity" => ['Min quantity must be at least 1.'],
                    ]);
                }

                if ($tierType === PriceTierType::FixedUnit) {
                    if ($unitPrice === null || (float) $unitPrice < 0) {
                        throw ValidationException::withMessages([
                            "price_tiers.{$index}.unit_price" => ['Unit price must be zero or greater for fixed tiers.'],
                        ]);
                    }
                } else {
                    if ($discountPercent === null || (float) $discountPercent < 0 || (float) $discountPercent > 100) {
                        throw ValidationException::withMessages([
                            "price_tiers.{$index}.discount_percent" => ['Discount percent must be between 0 and 100.'],
                        ]);
                    }
                }

                if (isset($seenMins[$minQuantity])) {
                    throw ValidationException::withMessages([
                        "price_tiers.{$index}.min_quantity" => ['Duplicate min_quantity in price tiers.'],
                    ]);
                }

                $seenMins[$minQuantity] = true;
            }

            $query = ConfigurationPriceTier::query()->where('product_id', $product->id);

            if ($configuration !== null) {
                $query->where('product_variant_id', $configuration->id);
            } else {
                $query->whereNull('product_variant_id');
            }

            $query->delete();

            foreach ($tiers as $tier) {
                $tierType = PriceTierType::tryFromMixed($tier['tier_type'] ?? null);

                ConfigurationPriceTier::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $configuration?->id,
                    'min_quantity' => (int) $tier['min_quantity'],
                    'tier_type' => $tierType,
                    'unit_price' => $tierType === PriceTierType::FixedUnit
                        ? $tier['unit_price']
                        : 0,
                    'discount_percent' => $tierType === PriceTierType::PercentOff
                        ? $tier['discount_percent']
                        : null,
                ]);
            }
        });
    }
}
