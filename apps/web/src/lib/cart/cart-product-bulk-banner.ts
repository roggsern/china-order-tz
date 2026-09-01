import {
  cartBulkHeadline,
  cartNextTierUnlockMessage,
  parseVolumeMoney,
  remainingToNextTier,
  volumePricingUnlocked,
  type VolumePricing,
} from "@/lib/pricing/volume-pricing";
import type { CartLineItem } from "@/lib/types/cart";

export type CartGroupBulkPresentation = {
  pricing: VolumePricing;
  mixedVariants: boolean;
  mixedUnitPrices: boolean;
  unlocked: boolean;
  nextMessage: string | null;
  savingsTotal: number;
};

function siblingContracts(items: CartLineItem[]): Array<VolumePricing | null | undefined> {
  return items.map((item) => item.volumePricing);
}

/**
 * Group-level next-tier copy is only valid when every sibling reports the same
 * server next threshold. Never pick first/min/max when they disagree.
 */
export function siblingsAgreeOnNextTier(
  items: CartLineItem[],
): { min_quantity: number; quantity_to_next_tier: number } | null {
  if (items.length === 0) return null;

  const contracts = siblingContracts(items);
  if (contracts.some((contract) => contract == null)) return null;

  const first = contracts[0];
  if (!first?.next_tier) return null;

  const firstMin = first.next_tier.min_quantity;
  const firstRemaining = first.quantity_to_next_tier;
  if (firstRemaining == null || firstRemaining <= 0) return null;

  for (const contract of contracts) {
    if (!contract?.next_tier) return null;
    if (contract.next_tier.min_quantity !== firstMin) return null;
    if (contract.quantity_to_next_tier !== firstRemaining) return null;
  }

  return {
    min_quantity: firstMin,
    quantity_to_next_tier: firstRemaining,
  };
}

export function hasMixedResolvedUnitPrices(items: CartLineItem[]): boolean {
  const resolved = new Set(
    items.map((item) =>
      item.volumePricing
        ? item.volumePricing.resolved_unit_price
        : item.unitPrice.toFixed(2),
    ),
  );
  return resolved.size > 1;
}

export function groupNextTierUnlockMessage(
  items: CartLineItem[],
  formatMoney: (amount: number) => string,
): string | null {
  const agreed = siblingsAgreeOnNextTier(items);
  if (!agreed) return null;

  const sample = items.find((item) => item.volumePricing)?.volumePricing;
  if (!sample || remainingToNextTier(sample) == null) return null;

  const mixedVariants = sample.aggregates_variants || items.length > 1;
  const message = cartNextTierUnlockMessage(sample, mixedVariants, formatMoney);
  if (!message) return null;

  if (mixedVariants && /each$/i.test(message.trim())) {
    return null;
  }

  return message;
}

export function resolveCartGroupBulkPresentation(
  items: CartLineItem[],
  formatMoney: (amount: number) => string,
): CartGroupBulkPresentation | null {
  const pricing = items.find((item) => item.volumePricing)?.volumePricing ?? null;
  if (!pricing || pricing.tiers.length === 0) return null;

  const mixedVariants = pricing.aggregates_variants || items.length > 1;
  const mixedUnitPrices = hasMixedResolvedUnitPrices(items);
  const unlocked = items.some((item) => volumePricingUnlocked(item.volumePricing));
  const savingsTotal = items.reduce(
    (sum, item) => sum + parseVolumeMoney(item.volumePricing?.savings_total),
    0,
  );

  return {
    pricing,
    mixedVariants,
    mixedUnitPrices,
    unlocked,
    nextMessage: groupNextTierUnlockMessage(items, formatMoney),
    savingsTotal,
  };
}

export { cartBulkHeadline };
