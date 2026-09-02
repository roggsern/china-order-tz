import type { ProductPriceTierDraft } from "@/lib/types/catalog";

export const VOLUME_PRICING_EDITOR_TITLE = "Bulk / Volume Pricing";

export const VOLUME_PRICING_EDITOR_DESCRIPTION =
  "Set quantity thresholds that reduce the unit price. These thresholds do not restrict which quantities customers can buy. Use Purchase Quantity Rules for allowed quantities.";

export type VolumePriceTierPayload = {
  min_quantity: number;
  tier_type: "fixed_unit" | "percent_off";
  unit_price?: number | null;
  discount_percent?: number | null;
};

export type AdminApiVolumePriceTier = {
  id?: string;
  configuration_id?: string | null;
  min_quantity: number;
  tier_type?: string | null;
  unit_price?: string | number | null;
  discount_percent?: string | number | null;
};

export type VolumePricingFormErrors = {
  form?: string;
  tiers?: string[];
};

function isProductLevelTier(tier: AdminApiVolumePriceTier): boolean {
  return !tier.configuration_id;
}

function parseOptionalNumber(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  const parsed = typeof value === "number" ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

export function mapApiPriceTierToDraft(tier: AdminApiVolumePriceTier): ProductPriceTierDraft {
  const tierType =
    tier.tier_type === "percent_off" ? ("percent_off" as const) : ("fixed_unit" as const);

  return {
    id: tier.id,
    minQuantity: Number(tier.min_quantity) || 1,
    tierType,
    unitPrice: parseOptionalNumber(tier.unit_price),
    discountPercent: parseOptionalNumber(tier.discount_percent),
  };
}

export function sortVolumeTiers(
  tiers: ProductPriceTierDraft[],
): ProductPriceTierDraft[] {
  return [...tiers].sort((left, right) => left.minQuantity - right.minQuantity);
}

export function mapProductLevelPriceTiers(
  tiers: AdminApiVolumePriceTier[] | undefined,
): ProductPriceTierDraft[] {
  return sortVolumeTiers(
    (tiers ?? []).filter(isProductLevelTier).map(mapApiPriceTierToDraft),
  );
}

export function hasConfigurationPriceTiers(
  productTiers: AdminApiVolumePriceTier[] | undefined,
  configurationRows: Array<{ price_tiers?: AdminApiVolumePriceTier[] }> | undefined,
): boolean {
  if ((productTiers ?? []).some((tier) => Boolean(tier.configuration_id) && Number(tier.min_quantity) >= 1)) {
    return true;
  }

  return (configurationRows ?? []).some((row) =>
    (row.price_tiers ?? []).some((tier) => Number(tier.min_quantity) >= 1),
  );
}

export function mapTierDraftToPayload(tier: ProductPriceTierDraft): VolumePriceTierPayload {
  if (tier.tierType === "percent_off") {
    return {
      min_quantity: Math.max(1, Math.floor(tier.minQuantity || 1)),
      tier_type: "percent_off",
      discount_percent: Math.max(0, Math.min(100, Number(tier.discountPercent) || 0)),
      unit_price: null,
    };
  }

  return {
    min_quantity: Math.max(1, Math.floor(tier.minQuantity || 1)),
    tier_type: "fixed_unit",
    unit_price: Math.max(0, Number(tier.unitPrice) || 0),
    discount_percent: null,
  };
}

export function starterVolumePricingTier(
  basePrice: number,
  minQuantity = 10,
): ProductPriceTierDraft {
  return {
    minQuantity,
    tierType: "fixed_unit",
    unitPrice: Math.max(0, Math.round(basePrice * 0.8)),
    discountPercent: null,
  };
}

/** Disabled keeps draft rows in UI state; save still sends an explicit product-level clear. */
export function applyVolumePricingEnabledChange(options: {
  nextEnabled: boolean;
  tiers: ProductPriceTierDraft[];
  basePrice: number;
}): { enabled: boolean; tiers: ProductPriceTierDraft[] } {
  if (!options.nextEnabled) {
    return { enabled: false, tiers: options.tiers };
  }

  if (options.tiers.length === 0) {
    return { enabled: true, tiers: [starterVolumePricingTier(options.basePrice)] };
  }

  return { enabled: true, tiers: options.tiers };
}

export function volumePricingWriteFields(options: {
  loaded: boolean;
  enabled: boolean;
  tiers: ProductPriceTierDraft[];
}): { price_tiers?: VolumePriceTierPayload[] } {
  if (!options.loaded) {
    return {};
  }

  if (!options.enabled) {
    return { price_tiers: [] };
  }

  return {
    price_tiers: sortVolumeTiers(options.tiers)
      .filter((tier) => tier.minQuantity >= 1)
      .map(mapTierDraftToPayload),
  };
}

export function volumePricingFormErrors(
  enabled: boolean,
  tiers: ProductPriceTierDraft[],
): VolumePricingFormErrors {
  if (!enabled) {
    return {};
  }

  if (tiers.length === 0) {
    return { form: "Add at least one bulk pricing tier, or disable bulk / volume pricing." };
  }

  const seen = new Map<number, number>();
  const tierErrors: string[] = [];

  tiers.forEach((tier, index) => {
    if (!Number.isInteger(tier.minQuantity) || tier.minQuantity < 1) {
      tierErrors[index] = "Minimum quantity must be a whole number of at least 1.";
      return;
    }

    const previousIndex = seen.get(tier.minQuantity);
    if (previousIndex !== undefined) {
      tierErrors[index] = "Each tier needs a unique minimum quantity.";
      if (!tierErrors[previousIndex]) {
        tierErrors[previousIndex] = "Each tier needs a unique minimum quantity.";
      }
      return;
    }
    seen.set(tier.minQuantity, index);

    if (tier.tierType === "percent_off") {
      if (
        tier.discountPercent === null ||
        !Number.isFinite(tier.discountPercent) ||
        tier.discountPercent < 0 ||
        tier.discountPercent > 100
      ) {
        tierErrors[index] = "Discount percent must be between 0 and 100.";
      }
      return;
    }

    if (tier.unitPrice === null || !Number.isFinite(tier.unitPrice) || tier.unitPrice < 0) {
      tierErrors[index] = "Unit price must be zero or greater.";
    }
  });

  if (tierErrors.some((message) => Boolean(message))) {
    return { tiers: tierErrors };
  }

  return {};
}

export function firstVolumePricingFormError(
  enabled: boolean,
  tiers: ProductPriceTierDraft[],
): string | undefined {
  const errors = volumePricingFormErrors(enabled, tiers);
  return errors.form ?? errors.tiers?.find((message) => Boolean(message));
}

export function inferredVolumeRangeLabels(
  tiers: ProductPriceTierDraft[],
  formatUnitPrice: (amount: number) => string,
): string[] {
  const sorted = sortVolumeTiers(
    tiers.filter((tier) => Number.isInteger(tier.minQuantity) && tier.minQuantity >= 1),
  );

  return sorted.map((tier, index) => {
    const next = sorted[index + 1];
    const priceLabel =
      tier.tierType === "percent_off" && tier.discountPercent != null
        ? `${tier.discountPercent}% off`
        : formatUnitPrice(tier.unitPrice ?? 0);
    const end = next ? next.minQuantity - 1 : null;

    if (end != null && end >= tier.minQuantity) {
      return `${tier.minQuantity}–${end} uses ${priceLabel}`;
    }

    return `${tier.minQuantity}+ uses ${priceLabel}`;
  });
}
