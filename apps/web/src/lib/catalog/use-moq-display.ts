"use client";

import { mapVolumePricing, parseVolumeMoney, volumePricingUnlocked } from "@/lib/pricing/volume-pricing";
import type { StorefrontPriceQuote } from "@/lib/catalog/storefront-configuration";
import type { CartMoqHint } from "@/lib/cart/quote";

/**
 * Display helpers from server volume_pricing. Does not calculate payable prices.
 */
export function useMoqDisplayState(input: {
  quote: StorefrontPriceQuote | null;
  slug: string;
  configurationId: string | null;
  quantity: number;
  stock: number;
  enabled: boolean;
}) {
  const { quote, enabled } = input;
  const volume = enabled ? mapVolumePricing(quote?.volume_pricing) : null;
  const unitPrice = quote ? Number.parseFloat(quote.unit_price) : null;
  const compareAtUnitPrice = volume ? parseVolumeMoney(volume.base_unit_price) : null;
  const unlocked = volumePricingUnlocked(volume);

  const remaining = volume?.quantity_to_next_tier;
  const next = volume?.next_tier;
  const moqHint: CartMoqHint | null =
    !unlocked && volume && remaining != null && remaining > 0 && next
      ? {
          remainingQuantity: remaining,
          targetQuantity: next.min_quantity,
          nextUnitPrice: parseVolumeMoney(next.unit_price),
          currentUnitPrice: unitPrice ?? parseVolumeMoney(volume.resolved_unit_price),
          savingsPerUnit: parseVolumeMoney(volume.savings_per_unit),
          totalSavings: parseVolumeMoney(volume.savings_total),
          baselineUnitPrice: parseVolumeMoney(volume.base_unit_price),
        }
      : null;

  return {
    unitPrice,
    compareAtUnitPrice,
    wholesaleApplied: unlocked,
    moqHint,
    moqDiscount: unlocked ? parseVolumeMoney(volume?.savings_total) : 0,
    moqPlan: volume
      ? {
          targetQuantity: next?.min_quantity ?? volume.current_tier?.min_quantity ?? 0,
          targetUnitPrice: parseVolumeMoney(next?.unit_price ?? volume.resolved_unit_price),
          baselineUnitPrice: parseVolumeMoney(volume.base_unit_price),
          remainingQuantity: remaining ?? 0,
          savingsPerUnit: parseVolumeMoney(volume.savings_per_unit),
          totalSavings: parseVolumeMoney(volume.savings_total),
        }
      : null,
    volumePricing: volume,
  };
}
