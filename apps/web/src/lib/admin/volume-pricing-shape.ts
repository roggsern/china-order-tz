import type { ProductPriceTierDraft } from "@/lib/types/catalog";
import type { AdminProductVariant, AdminVariantPrice } from "@/lib/api/admin-catalog";

export const VOLUME_PRICING_SEPARATION_NOTE =
  "Bulk / Volume Pricing controls quantity-based customer pricing. This is separate from legacy variant price types such as Wholesale / Dealer / VIP.";

export const VOLUME_PRICING_SAME_RETAIL_NOTE =
  "All active variants currently share the same retail price. One bulk schedule can safely apply to all variants.";

export const VOLUME_PRICING_DIFFERENT_RETAIL_NOTE =
  "Variant prices differ. A single fixed unit price would override those differences.";

export const VOLUME_PRICING_UNKNOWN_RETAIL_NOTE =
  "Variant retail prices could not be compared safely. A single fixed unit price is blocked until each active variant has a current TZS retail amount.";

export const VOLUME_PRICING_LEGACY_FIXED_NOTE =
  "This product has different variant retail prices but currently uses an all-variant fixed unit bulk price.";

export const VOLUME_PRICING_VARIANT_OVERRIDE_NOTE =
  "Variant-specific pricing overrides the all-variant schedule for that variant.";

export const VOLUME_PRICING_CREATE_VARIANT_NOTE =
  "Create the product first, then configure variant-specific bulk pricing in Product Edit. A percentage schedule can still be set here.";

export type VolumePriceShapeKind =
  | "simple"
  | "configurable_same_retail"
  | "configurable_different_retail"
  | "unknown";

export type VolumeRetailObservation = {
  variantId: string;
  isActive: boolean;
  amountMinor: number | null;
};

export type VolumePriceShape = {
  kind: VolumePriceShapeKind;
  relevantVariantIds: string[];
  amountsMinor: number[];
  allowsProductFixedUnit: boolean;
  allowsProductPercentOff: boolean;
  allowsVariantSchedules: boolean;
  hasLegacyProductFixedUnitRisk: boolean;
  note: string | null;
};

/** Compare TZS amounts as integer minor units so display formatting cannot leak in. */
export function normalizeTzsMinor(amount: number | null | undefined): number | null {
  if (amount === null || amount === undefined || !Number.isFinite(amount) || amount <= 0) {
    return null;
  }

  return Math.round(amount * 100);
}

export function activeRetailAmountTzs(prices: AdminVariantPrice[]): number | null {
  const retail = prices
    .filter(
      (price) =>
        price.priceType === "retail" &&
        price.isCurrentlyActive &&
        price.currency.trim().toUpperCase() === "TZS" &&
        Number.isFinite(price.amount) &&
        price.amount > 0,
    )
    .sort((left, right) => left.minimumQuantity - right.minimumQuantity)[0];

  return retail ? normalizeTzsMinor(retail.amount) : null;
}

export function variantVolumeLabel(variant: Pick<
  AdminProductVariant,
  "id" | "name" | "sku" | "displayAttributes"
>): string {
  const fromAttributes = variant.displayAttributes
    .map((row) => row.value.trim())
    .filter((value) => value !== "")
    .join(" / ");
  if (fromAttributes !== "") {
    return fromAttributes;
  }

  const name = variant.name?.trim();
  if (name) {
    return name;
  }

  const sku = variant.sku.trim();
  if (sku !== "") {
    return sku;
  }

  return "Variant";
}

/**
 * Relevant variants = currently active (`isActive`).
 * Inactive / retired rows are ignored, matching admin price_range pooling.
 * Stock is not required: a priced inactive-stock SKU still has a retail amount.
 */
export function relevantVolumeVariants<T extends { id: string; isActive: boolean }>(
  variants: T[],
): T[] {
  return variants.filter((variant) => variant.isActive);
}

export function classifyVolumePriceShape(input: {
  pricingModel: "simple" | "variants" | null | undefined;
  variants: VolumeRetailObservation[];
  productLevelTiers?: ProductPriceTierDraft[];
}): VolumePriceShape {
  const relevant = input.variants.filter((variant) => variant.isActive);
  const configurable =
    input.pricingModel === "variants" || input.variants.length > 0;

  if (!configurable) {
    return {
      kind: "simple",
      relevantVariantIds: [],
      amountsMinor: [],
      allowsProductFixedUnit: true,
      allowsProductPercentOff: true,
      allowsVariantSchedules: false,
      hasLegacyProductFixedUnitRisk: false,
      note: null,
    };
  }

  if (relevant.length === 0) {
    return unknownShape([], true);
  }

  const amounts = relevant.map((variant) => variant.amountMinor);
  if (amounts.some((amount) => amount === null)) {
    return unknownShape(
      relevant.map((variant) => variant.variantId),
      hasProductFixedUnit(input.productLevelTiers),
    );
  }

  const unique = new Set(amounts as number[]);
  const same = unique.size === 1;
  const legacyFixed = !same && hasProductFixedUnit(input.productLevelTiers);

  if (same) {
    return {
      kind: "configurable_same_retail",
      relevantVariantIds: relevant.map((variant) => variant.variantId),
      amountsMinor: [...unique],
      allowsProductFixedUnit: true,
      allowsProductPercentOff: true,
      allowsVariantSchedules: true,
      hasLegacyProductFixedUnitRisk: false,
      note: VOLUME_PRICING_SAME_RETAIL_NOTE,
    };
  }

  return {
    kind: "configurable_different_retail",
    relevantVariantIds: relevant.map((variant) => variant.variantId),
    amountsMinor: [...unique].sort((left, right) => left - right),
    allowsProductFixedUnit: false,
    allowsProductPercentOff: true,
    allowsVariantSchedules: true,
    hasLegacyProductFixedUnitRisk: legacyFixed,
    note: legacyFixed
      ? VOLUME_PRICING_LEGACY_FIXED_NOTE
      : VOLUME_PRICING_DIFFERENT_RETAIL_NOTE,
  };
}

function unknownShape(
  relevantVariantIds: string[],
  hasLegacyProductFixedUnitRisk: boolean,
): VolumePriceShape {
  return {
    kind: "unknown",
    relevantVariantIds,
    amountsMinor: [],
    allowsProductFixedUnit: false,
    allowsProductPercentOff: true,
    allowsVariantSchedules: true,
    hasLegacyProductFixedUnitRisk,
    note: hasLegacyProductFixedUnitRisk
      ? VOLUME_PRICING_LEGACY_FIXED_NOTE
      : VOLUME_PRICING_UNKNOWN_RETAIL_NOTE,
  };
}

export function hasProductFixedUnit(tiers: ProductPriceTierDraft[] | undefined): boolean {
  return (tiers ?? []).some(
    (tier) => tier.tierType === "fixed_unit" && Number.isInteger(tier.minQuantity) && tier.minQuantity >= 1,
  );
}

export function productVolumeAllowedTierTypes(
  shape: VolumePriceShape,
): Array<"fixed_unit" | "percent_off"> {
  if (shape.allowsProductFixedUnit) {
    return ["fixed_unit", "percent_off"];
  }

  return ["percent_off"];
}

export type VolumePricingView = "product" | "variant" | "keep";

export function formatRetailTzsFromMinor(amountMinor: number | null | undefined): string | null {
  if (amountMinor === null || amountMinor === undefined || !Number.isFinite(amountMinor)) {
    return null;
  }

  return `TZS ${Math.round(amountMinor / 100).toLocaleString("en-US")}`;
}

export function initialVolumePricingView(options: {
  shape: VolumePriceShape;
  hasVariantSchedules: boolean;
}): VolumePricingView {
  if (options.shape.hasLegacyProductFixedUnitRisk) {
    return "keep";
  }

  if (
    (options.shape.kind === "configurable_different_retail" || options.shape.kind === "unknown") &&
    options.hasVariantSchedules
  ) {
    return "variant";
  }

  return "product";
}

export function volumePricingContextNotes(options: {
  shape: VolumePriceShape;
  view: VolumePricingView;
  hasVariantSchedules: boolean;
  hasProductSchedule: boolean;
}): string[] {
  const notes: string[] = [VOLUME_PRICING_SEPARATION_NOTE];

  if (options.shape.note) {
    notes.push(options.shape.note);
  }

  if (
    options.view === "variant" ||
    (options.hasVariantSchedules && options.hasProductSchedule)
  ) {
    notes.push(VOLUME_PRICING_VARIANT_OVERRIDE_NOTE);
  }

  return notes;
}
