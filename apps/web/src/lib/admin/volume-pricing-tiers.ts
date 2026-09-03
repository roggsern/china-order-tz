import type { ProductPriceTierDraft } from "@/lib/types/catalog";
import type { VolumePricingView } from "./volume-pricing-shape";

export type { VolumePricingView };

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

export type VolumePricingTierType = ProductPriceTierDraft["tierType"];

export function starterVolumePricingTier(
  basePrice: number,
  minQuantity = 10,
  tierType: VolumePricingTierType = "fixed_unit",
): ProductPriceTierDraft {
  if (tierType === "percent_off") {
    return {
      minQuantity,
      tierType: "percent_off",
      unitPrice: null,
      discountPercent: 10,
    };
  }

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
  starterTierType?: VolumePricingTierType;
}): { enabled: boolean; tiers: ProductPriceTierDraft[] } {
  if (!options.nextEnabled) {
    return { enabled: false, tiers: options.tiers };
  }

  if (options.tiers.length === 0) {
    return {
      enabled: true,
      tiers: [starterVolumePricingTier(options.basePrice, 10, options.starterTierType ?? "fixed_unit")],
    };
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
  options?: { allowedTierTypes?: VolumePricingTierType[] },
): VolumePricingFormErrors {
  if (!enabled) {
    return {};
  }

  if (tiers.length === 0) {
    return { form: "Add at least one bulk pricing tier, or disable bulk / volume pricing." };
  }

  const allowed = options?.allowedTierTypes;
  if (allowed && allowed.length > 0) {
    const disallowed = tiers.find((tier) => !allowed.includes(tier.tierType));
    if (disallowed) {
      return {
        form: "Use percentage off. A fixed unit price would override variant retail differences.",
      };
    }
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
  options?: { allowedTierTypes?: VolumePricingTierType[] },
): string | undefined {
  const errors = volumePricingFormErrors(enabled, tiers, options);
  return errors.form ?? errors.tiers?.find((message) => Boolean(message));
}

export type VariantVolumeDraft = {
  enabled: boolean;
  tiers: ProductPriceTierDraft[];
};

export function emptyVariantVolumeDraft(): VariantVolumeDraft {
  return { enabled: false, tiers: [] };
}

export function variantVolumeDraftFromTiers(tiers: ProductPriceTierDraft[]): VariantVolumeDraft {
  const sorted = sortVolumeTiers(tiers);
  return { enabled: sorted.length > 0, tiers: sorted };
}

export function mapVariantVolumeSchedules(
  productTiers: AdminApiVolumePriceTier[] | undefined,
  configurationRows: Array<{ id: string; price_tiers?: AdminApiVolumePriceTier[] }> | undefined,
): Record<string, ProductPriceTierDraft[]> {
  const schedules: Record<string, ProductPriceTierDraft[]> = {};

  for (const row of configurationRows ?? []) {
    schedules[row.id] = sortVolumeTiers((row.price_tiers ?? []).map(mapApiPriceTierToDraft));
  }

  for (const tier of productTiers ?? []) {
    const configurationId = tier.configuration_id;
    if (!configurationId) {
      continue;
    }

    const draft = mapApiPriceTierToDraft(tier);
    const existing = schedules[configurationId] ?? [];
    const alreadyMapped = draft.id
      ? existing.some((row) => row.id === draft.id)
      : existing.some(
          (row) =>
            row.minQuantity === draft.minQuantity &&
            row.tierType === draft.tierType &&
            row.unitPrice === draft.unitPrice &&
            row.discountPercent === draft.discountPercent,
        );
    if (!alreadyMapped) {
      schedules[configurationId] = sortVolumeTiers([...existing, draft]);
    }
  }

  return schedules;
}

function serializeVariantVolumeDraft(draft: VariantVolumeDraft): string {
  return JSON.stringify({
    enabled: draft.enabled,
    tiers: sortVolumeTiers(draft.tiers).map((tier) => ({
      minQuantity: tier.minQuantity,
      tierType: tier.tierType,
      unitPrice: tier.unitPrice,
      discountPercent: tier.discountPercent,
    })),
  });
}

export function variantVolumeDraftsEqual(
  left: VariantVolumeDraft | undefined,
  right: VariantVolumeDraft | undefined,
): boolean {
  return (
    serializeVariantVolumeDraft(left ?? emptyVariantVolumeDraft()) ===
    serializeVariantVolumeDraft(right ?? emptyVariantVolumeDraft())
  );
}

export function variantVolumeWriteFields(draft: VariantVolumeDraft): VolumePriceTierPayload[] {
  if (!draft.enabled) {
    return [];
  }

  return sortVolumeTiers(draft.tiers)
    .filter((tier) => tier.minQuantity >= 1)
    .map(mapTierDraftToPayload);
}

export type VariantVolumeWrite = {
  configurationId: string;
  priceTiers: VolumePriceTierPayload[];
};

export function collectVariantVolumeWrites(options: {
  view: VolumePricingView;
  loaded: boolean;
  relevantVariantIds: string[];
  drafts: Record<string, VariantVolumeDraft>;
  initial: Record<string, VariantVolumeDraft>;
}): VariantVolumeWrite[] {
  if (!options.loaded || options.view !== "variant") {
    return [];
  }

  return options.relevantVariantIds.flatMap((variantId) => {
    const draft = options.drafts[variantId] ?? emptyVariantVolumeDraft();
    const initial = options.initial[variantId] ?? emptyVariantVolumeDraft();
    if (variantVolumeDraftsEqual(draft, initial)) {
      return [];
    }

    return [{ configurationId: variantId, priceTiers: variantVolumeWriteFields(draft) }];
  });
}

export function productVolumeWriteShouldInclude(options: {
  view: VolumePricingView;
  writeProduct: boolean;
}): boolean {
  return options.view !== "keep" && options.writeProduct;
}

export function canonicalVolumePricingValidationError(options: {
  view: VolumePricingView;
  writeProduct: boolean;
  productEnabled: boolean;
  productTiers: ProductPriceTierDraft[];
  allowedProductTierTypes?: VolumePricingTierType[];
  relevantVariantIds: string[];
  variantDrafts: Record<string, VariantVolumeDraft>;
}): string | undefined {
  if (options.view === "keep") {
    return undefined;
  }

  if (options.view === "product" || options.writeProduct) {
    const error = firstVolumePricingFormError(
      options.productEnabled,
      options.productTiers,
      { allowedTierTypes: options.allowedProductTierTypes },
    );
    if (error) {
      return error;
    }
  }

  if (options.view === "variant") {
    for (const variantId of options.relevantVariantIds) {
      const draft = options.variantDrafts[variantId] ?? emptyVariantVolumeDraft();
      const error = firstVolumePricingFormError(draft.enabled, draft.tiers);
      if (error) {
        return error;
      }
    }
  }

  return undefined;
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
