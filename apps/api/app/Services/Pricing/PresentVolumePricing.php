<?php

namespace App\Services\Pricing;

use App\Enums\PriceTierType;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\DTOs\VolumePricingPresentation;
use Illuminate\Support\Collection;

/**
 * Display-only volume-tier schedule from configuration_price_tiers.
 * Payable unit price stays on CommercePricingResolver / cart unit_price.
 */
final class PresentVolumePricing
{
    public function present(
        Product $product,
        ?ProductVariant $variant,
        int $eligibleQuantity,
        string $baseUnitPrice,
        string $resolvedUnitPrice,
        int $lineQuantity,
        string $currency = 'TZS',
    ): ?VolumePricingPresentation {
        $product->loadMissing('productType');

        if ($product->productType && ! $product->productType->allows_moq_pricing) {
            return null;
        }

        $tiers = $this->visibleTiers($product, $variant);
        if ($tiers->isEmpty()) {
            return null;
        }

        $eligibleQuantity = max(0, $eligibleQuantity);
        $lineQuantity = max(1, $lineQuantity);
        $base = $this->formatAmount($baseUnitPrice);
        $resolved = $this->formatAmount($resolvedUnitPrice);

        $presented = $tiers
            ->map(fn (ConfigurationPriceTier $tier) => $this->tierArray($tier, $base))
            ->values()
            ->all();

        $current = $this->currentTier($presented, $eligibleQuantity);
        $next = $this->nextTier($presented, $eligibleQuantity);

        $savingsPerUnit = max(0, (float) $base - (float) $resolved);
        $savingsTotal = $savingsPerUnit * $lineQuantity;

        return new VolumePricingPresentation(
            eligibleQuantity: $eligibleQuantity,
            aggregatesVariants: $this->aggregatesVariants($product),
            currentTier: $current,
            nextTier: $next,
            quantityToNextTier: $next === null
                ? null
                : max(0, (int) $next['min_quantity'] - $eligibleQuantity),
            baseUnitPrice: $base,
            resolvedUnitPrice: $resolved,
            savingsPerUnit: $this->formatAmount($savingsPerUnit),
            savingsTotal: $this->formatAmount($savingsTotal),
            tiers: $presented,
            currency: strtoupper($currency),
        );
    }

    /**
     * Honest schedule matching CommercePricingResolver::resolveTier precedence:
     * variant-scoped tiers win whenever quantity reaches them; product-level
     * tiers only apply below the lowest variant min_quantity.
     *
     * @return Collection<int, ConfigurationPriceTier>
     */
    private function visibleTiers(Product $product, ?ProductVariant $variant): Collection
    {
        $productTiers = ConfigurationPriceTier::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->orderBy('min_quantity')
            ->get();

        if ($variant === null) {
            return $productTiers;
        }

        $variantTiers = ConfigurationPriceTier::query()
            ->where('product_variant_id', $variant->id)
            ->orderBy('min_quantity')
            ->get();

        $lowestVariantMin = $variantTiers->min('min_quantity');
        $visibleProduct = $lowestVariantMin === null
            ? $productTiers
            : $productTiers->filter(
                fn (ConfigurationPriceTier $tier) => $tier->min_quantity < (int) $lowestVariantMin,
            );

        return $visibleProduct
            ->concat($variantTiers)
            ->sortBy('min_quantity')
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $tiers
     * @return array<string, mixed>|null
     */
    private function currentTier(array $tiers, int $eligibleQuantity): ?array
    {
        $current = null;
        foreach ($tiers as $tier) {
            if ((int) $tier['min_quantity'] <= $eligibleQuantity) {
                $current = $tier;
            }
        }

        return $current;
    }

    /**
     * @param  list<array<string, mixed>>  $tiers
     * @return array<string, mixed>|null
     */
    private function nextTier(array $tiers, int $eligibleQuantity): ?array
    {
        foreach ($tiers as $tier) {
            if ((int) $tier['min_quantity'] > $eligibleQuantity) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     min_quantity: int,
     *     unit_price: string,
     *     type: string,
     *     discount_percent: string|null,
     *     scope: string
     * }
     */
    private function tierArray(ConfigurationPriceTier $tier, string $baseUnitPrice): array
    {
        $type = $tier->tier_type ?? PriceTierType::FixedUnit;

        return [
            'min_quantity' => (int) $tier->min_quantity,
            'unit_price' => $tier->resolveUnitPrice($baseUnitPrice),
            'type' => $type->value,
            'discount_percent' => $type === PriceTierType::PercentOff
                ? $this->formatAmount($tier->discount_percent ?? 0)
                : null,
            'scope' => filled($tier->product_variant_id) ? 'configuration' : 'product',
        ];
    }

    private function aggregatesVariants(Product $product): bool
    {
        if (isset($product->variants_count)) {
            return (int) $product->variants_count > 1;
        }

        if ($product->relationLoaded('variants')) {
            return $product->variants->where('is_active', true)->count() > 1;
        }

        return $product->variants()->where('is_active', true)->count() > 1;
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
