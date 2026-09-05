import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import {
  parseVolumeMoney,
  remainingToNextTier,
  type VolumePricing,
  type VolumePricingTier,
  type VolumePricingTierType,
} from './mapVolumePricing';

export type VolumePricingDisplayRow = {
  key: string;
  minQuantity: number;
  maxQuantity: number | null;
  quantityLabel: string;
  unitPrice: string;
  type: VolumePricingTierType;
  discountPercent: string | null;
  active: boolean;
  isOpeningBand: boolean;
};

function uniqueSortedTiers(tiers: VolumePricingTier[]): VolumePricingTier[] {
  const seen = new Set<number>();
  return [...tiers]
    .sort((left, right) => left.min_quantity - right.min_quantity)
    .filter((tier) => {
      if (seen.has(tier.min_quantity)) return false;
      seen.add(tier.min_quantity);
      return true;
    });
}

/** Present consecutive server mins as ranges. Does not invent extra thresholds. */
export function formatVolumeQuantityRange(
  minQuantity: number,
  maxQuantity: number | null,
): string {
  if (maxQuantity == null) {
    return `${minQuantity}+ pcs`;
  }
  if (minQuantity === maxQuantity) {
    return `${minQuantity} pcs`;
  }
  return `${minQuantity}–${maxQuantity} pcs`;
}

function pricesEqual(left: string, right: string): boolean {
  return parseVolumeMoney(left) === parseVolumeMoney(right);
}

/**
 * Display rows from canonical volume_pricing.
 * Opening 1–(first.min−1) uses base_unit_price only when the first break is above 1
 * and is not the same price as the list unit (avoids a duplicate base row).
 */
export function presentVolumePricingRows(
  pricing: VolumePricing,
  quantity?: number,
): VolumePricingDisplayRow[] {
  const tiers = uniqueSortedTiers(pricing.tiers);
  if (tiers.length === 0) return [];

  const qty = quantity ?? pricing.eligible_quantity;
  const quantityMatchesEligible =
    quantity == null || quantity === pricing.eligible_quantity;
  const rows: VolumePricingDisplayRow[] = [];
  const first = tiers[0];

  if (
    first &&
    first.min_quantity > 1 &&
    !pricesEqual(first.unit_price, pricing.base_unit_price)
  ) {
    const maxQuantity = first.min_quantity - 1;
    rows.push({
      key: `opening-1-${maxQuantity}`,
      minQuantity: 1,
      maxQuantity,
      quantityLabel: formatVolumeQuantityRange(1, maxQuantity),
      unitPrice: pricing.base_unit_price,
      type: 'fixed_unit',
      discountPercent: null,
      active: quantityMatchesEligible
        ? pricing.current_tier == null
        : qty < first.min_quantity,
      isOpeningBand: true,
    });
  }

  for (let index = 0; index < tiers.length; index += 1) {
    const tier = tiers[index];
    const next = tiers[index + 1];
    const maxQuantity = next ? next.min_quantity - 1 : null;
    rows.push({
      key: `${tier.scope}-${tier.min_quantity}`,
      minQuantity: tier.min_quantity,
      maxQuantity,
      quantityLabel: formatVolumeQuantityRange(tier.min_quantity, maxQuantity),
      unitPrice: tier.unit_price,
      type: tier.type,
      discountPercent: tier.discount_percent,
      active: quantityMatchesEligible
        ? pricing.current_tier?.min_quantity === tier.min_quantity
        : qty >= tier.min_quantity && (next == null || qty < next.min_quantity),
      isOpeningBand: false,
    });
  }

  return rows;
}

function nextTierForQuantity(
  pricing: VolumePricing,
  quantity: number,
): VolumePricingTier | null {
  return (
    uniqueSortedTiers(pricing.tiers).find((tier) => tier.min_quantity > quantity) ??
    null
  );
}

/**
 * Next-tier helper from canonical schedule fields.
 * Uses server quantity_to_next_tier when the quote quantity matches.
 */
export function nextTierHelperMessage(
  pricing: VolumePricing,
  quantity?: number,
): string | null {
  const qty = quantity ?? pricing.eligible_quantity;
  const quantityMatchesEligible =
    quantity == null || quantity === pricing.eligible_quantity;

  let remaining: number | null;
  let next: VolumePricingTier | null;

  if (quantityMatchesEligible) {
    remaining = remainingToNextTier(pricing);
    next = pricing.next_tier;
  } else {
    next = nextTierForQuantity(pricing, qty);
    remaining = next == null ? null : next.min_quantity - qty;
  }

  if (remaining == null || remaining <= 0 || !next) {
    return null;
  }

  const more = remaining === 1 ? '1 more' : `${remaining} more`;
  if (next.type === 'percent_off' && next.discount_percent) {
    return `Add ${more} to get ${parseFloat(next.discount_percent)}% off`;
  }
  return `Add ${more} to get ${formatCustomerMoney(next.unit_price, pricing.currency)} each`;
}
