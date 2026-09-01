export type VolumePricingTierType = "fixed_unit" | "percent_off";

export type VolumePricingTier = {
  min_quantity: number;
  unit_price: string;
  type: VolumePricingTierType;
  discount_percent: string | null;
  scope: "product" | "configuration";
};

export type VolumePricing = {
  eligible_quantity: number;
  aggregates_variants: boolean;
  current_tier: VolumePricingTier | null;
  next_tier: VolumePricingTier | null;
  quantity_to_next_tier: number | null;
  base_unit_price: string;
  resolved_unit_price: string;
  savings_per_unit: string;
  savings_total: string;
  currency: string;
  tiers: VolumePricingTier[];
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function asString(value: unknown): string | null {
  if (typeof value === "string" && value.trim() !== "") return value;
  if (typeof value === "number" && Number.isFinite(value)) return String(value);
  return null;
}

function asInt(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) return Math.trunc(value);
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number.parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function mapTier(raw: unknown): VolumePricingTier | null {
  if (!isRecord(raw)) return null;
  const minQuantity = asInt(raw.min_quantity);
  const unitPrice = asString(raw.unit_price);
  const type = raw.type === "percent_off" ? "percent_off" : raw.type === "fixed_unit" ? "fixed_unit" : null;
  if (minQuantity == null || minQuantity < 1 || !unitPrice || !type) return null;

  return {
    min_quantity: minQuantity,
    unit_price: unitPrice,
    type,
    discount_percent: asString(raw.discount_percent),
    scope: raw.scope === "configuration" ? "configuration" : "product",
  };
}

/** Parse server volume_pricing. Returns null when absent or empty — never invents tiers. */
export function mapVolumePricing(raw: unknown): VolumePricing | null {
  if (!isRecord(raw)) return null;
  const eligible = asInt(raw.eligible_quantity);
  const base = asString(raw.base_unit_price);
  const resolved = asString(raw.resolved_unit_price);
  const tiers = Array.isArray(raw.tiers)
    ? raw.tiers.map(mapTier).filter((tier): tier is VolumePricingTier => tier !== null)
    : [];

  if (eligible == null || !base || !resolved || tiers.length === 0) {
    return null;
  }

  return {
    eligible_quantity: eligible,
    aggregates_variants: raw.aggregates_variants === true,
    current_tier: mapTier(raw.current_tier),
    next_tier: mapTier(raw.next_tier),
    quantity_to_next_tier: asInt(raw.quantity_to_next_tier),
    base_unit_price: base,
    resolved_unit_price: resolved,
    savings_per_unit: asString(raw.savings_per_unit) ?? "0.00",
    savings_total: asString(raw.savings_total) ?? "0.00",
    currency: asString(raw.currency) ?? "TZS",
    tiers,
  };
}

export function parseVolumeMoney(value: string | number | null | undefined): number {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

export function volumePricingUnlocked(pricing: VolumePricing | null | undefined): boolean {
  return Boolean(pricing?.current_tier);
}

export const MIXED_VARIANTS_BULK_NOTE =
  "Different variants of this product count together toward bulk pricing.";

export const BULK_SHIPPING_NOTE =
  "Bulk discounts apply to product prices only. Shipping is calculated separately.";

export function nextTierUnlockMessage(
  pricing: VolumePricing,
  formatMoney: (amount: number) => string,
): string | null {
  const remaining = remainingToNextTier(pricing);
  if (remaining == null || !pricing.next_tier) return null;
  const more = remaining === 1 ? "1 more" : `${remaining} more`;
  if (pricing.next_tier.type === "percent_off" && pricing.next_tier.discount_percent) {
    return `Add ${more} to unlock ${parseFloat(pricing.next_tier.discount_percent)}% off`;
  }
  return `Add ${more} to unlock ${formatMoney(parseFloat(pricing.next_tier.unit_price))} each`;
}

export function cartNextTierUnlockMessage(
  pricing: VolumePricing,
  mixedVariants: boolean,
  formatMoney: (amount: number) => string,
): string | null {
  const remaining = remainingToNextTier(pricing);
  if (remaining == null) return null;
  if (mixedVariants) {
    const more = remaining === 1 ? "1 more" : `${remaining} more`;
    return `Add ${more} of this product — any variant — to unlock the next bulk tier.`;
  }
  return nextTierUnlockMessage(pricing, formatMoney);
}

export function cartBulkHeadline(pricing: VolumePricing): string {
  const pieces = `${pricing.eligible_quantity} total ${
    pricing.eligible_quantity === 1 ? "piece" : "pieces"
  }`;
  if (volumePricingUnlocked(pricing)) {
    return `${pieces} — bulk tier unlocked`;
  }
  return pieces;
}

export function remainingToNextTier(pricing: VolumePricing | null | undefined): number | null {
  const remaining = pricing?.quantity_to_next_tier;
  if (remaining == null || remaining <= 0 || !pricing?.next_tier) return null;
  return remaining;
}
