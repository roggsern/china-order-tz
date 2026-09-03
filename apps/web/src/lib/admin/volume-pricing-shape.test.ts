import assert from "node:assert/strict";
import { test } from "node:test";
import type { AdminVariantPrice } from "@/lib/api/admin-catalog";
import {
  VOLUME_PRICING_DIFFERENT_RETAIL_NOTE,
  VOLUME_PRICING_LEGACY_FIXED_NOTE,
  VOLUME_PRICING_SAME_RETAIL_NOTE,
  VOLUME_PRICING_UNKNOWN_RETAIL_NOTE,
  VOLUME_PRICING_VARIANT_OVERRIDE_NOTE,
  activeRetailAmountTzs,
  classifyVolumePriceShape,
  initialVolumePricingView,
  normalizeTzsMinor,
  productVolumeAllowedTierTypes,
  variantVolumeLabel,
  volumePricingContextNotes,
} from "./volume-pricing-shape";
import { firstVolumePricingFormError } from "./volume-pricing-tiers";

function retail(amount: number, overrides: Partial<AdminVariantPrice> = {}): AdminVariantPrice {
  return {
    id: "price-1",
    productVariantId: "var-1",
    priceType: "retail",
    currency: "TZS",
    amount,
    compareAtPrice: null,
    costPrice: null,
    minimumQuantity: 1,
    isActive: true,
    isCurrentlyActive: true,
    startsAt: null,
    endsAt: null,
    ...overrides,
  };
}

test("simple product classification", () => {
  const shape = classifyVolumePriceShape({
    pricingModel: "simple",
    variants: [],
  });
  assert.equal(shape.kind, "simple");
  assert.equal(shape.allowsProductFixedUnit, true);
  assert.equal(shape.allowsProductPercentOff, true);
  assert.equal(shape.allowsVariantSchedules, false);
});

test("same-price configurable classification uses active variants only", () => {
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "red-m", isActive: true, amountMinor: 5_000_000 },
      { variantId: "green-m", isActive: true, amountMinor: 5_000_000 },
      { variantId: "red-xl", isActive: true, amountMinor: 5_000_000 },
      { variantId: "retired", isActive: false, amountMinor: 9_999_999 },
    ],
  });
  assert.equal(shape.kind, "configurable_same_retail");
  assert.equal(shape.note, VOLUME_PRICING_SAME_RETAIL_NOTE);
  assert.equal(shape.allowsProductFixedUnit, true);
  assert.deepEqual(shape.relevantVariantIds, ["red-m", "green-m", "red-xl"]);
});

test("different-price configurable classification", () => {
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "128", isActive: true, amountMinor: 200_000_000 },
      { variantId: "256", isActive: true, amountMinor: 230_000_000 },
    ],
  });
  assert.equal(shape.kind, "configurable_different_retail");
  assert.equal(shape.allowsProductFixedUnit, false);
  assert.equal(shape.note, VOLUME_PRICING_DIFFERENT_RETAIL_NOTE);
});

test("unknown or missing retail fails closed", () => {
  const missing = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "128", isActive: true, amountMinor: 200_000_000 },
      { variantId: "256", isActive: true, amountMinor: null },
    ],
  });
  assert.equal(missing.kind, "unknown");
  assert.equal(missing.allowsProductFixedUnit, false);
  assert.equal(missing.note, VOLUME_PRICING_UNKNOWN_RETAIL_NOTE);

  const noneActive = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [{ variantId: "old", isActive: false, amountMinor: 1 }],
  });
  assert.equal(noneActive.kind, "unknown");
  assert.equal(noneActive.allowsProductFixedUnit, false);
});

test("same-price variants allow product fixed_unit and percent_off", () => {
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "a", isActive: true, amountMinor: 5000000 },
      { variantId: "b", isActive: true, amountMinor: 5000000 },
    ],
  });
  assert.deepEqual(productVolumeAllowedTierTypes(shape), ["fixed_unit", "percent_off"]);
  assert.equal(firstVolumePricingFormError(true, [
    { minQuantity: 10, tierType: "fixed_unit", unitPrice: 45000, discountPercent: null },
  ], { allowedTierTypes: productVolumeAllowedTierTypes(shape) }), undefined);
  assert.equal(firstVolumePricingFormError(true, [
    { minQuantity: 10, tierType: "percent_off", unitPrice: null, discountPercent: 10 },
  ], { allowedTierTypes: productVolumeAllowedTierTypes(shape) }), undefined);
});

test("different-price variants block new product fixed_unit and allow percent_off", () => {
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "a", isActive: true, amountMinor: 200_000_000 },
      { variantId: "b", isActive: true, amountMinor: 230_000_000 },
    ],
  });
  const allowed = productVolumeAllowedTierTypes(shape);
  assert.deepEqual(allowed, ["percent_off"]);
  assert.match(
    firstVolumePricingFormError(
      true,
      [{ minQuantity: 10, tierType: "fixed_unit", unitPrice: 1_900_000, discountPercent: null }],
      { allowedTierTypes: allowed },
    ) ?? "",
    /percentage off/i,
  );
  assert.equal(
    firstVolumePricingFormError(
      true,
      [{ minQuantity: 10, tierType: "percent_off", unitPrice: null, discountPercent: 10 }],
      { allowedTierTypes: allowed },
    ),
    undefined,
  );
  assert.equal(shape.note, VOLUME_PRICING_DIFFERENT_RETAIL_NOTE);
});

test("existing product-level fixed_unit on different-price product is flagged not auto-deleted", () => {
  const tiers = [
    { minQuantity: 10, tierType: "fixed_unit" as const, unitPrice: 1_900_000, discountPercent: null },
  ];
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "a", isActive: true, amountMinor: 200_000_000 },
      { variantId: "b", isActive: true, amountMinor: 230_000_000 },
    ],
    productLevelTiers: tiers,
  });
  assert.equal(shape.hasLegacyProductFixedUnitRisk, true);
  assert.equal(shape.note, VOLUME_PRICING_LEGACY_FIXED_NOTE);
  assert.equal(tiers.length, 1);
});

test("activeRetailAmountTzs ignores wholesale and inactive retail", () => {
  assert.equal(
    activeRetailAmountTzs([
      retail(2600000, { priceType: "wholesale", minimumQuantity: 10 }),
      retail(2000000, { isCurrentlyActive: false }),
      retail(2300000, { minimumQuantity: 5 }),
      retail(2100000, { minimumQuantity: 1 }),
    ]),
    210_000_000,
  );
  assert.equal(activeRetailAmountTzs([]), null);
});

test("normalizeTzsMinor does not compare display strings", () => {
  assert.equal(normalizeTzsMinor(2000000), normalizeTzsMinor(2000000.004));
  assert.notEqual(normalizeTzsMinor(2000000), normalizeTzsMinor(2300000));
  assert.equal(normalizeTzsMinor(0), null);
});

test("variant labels prefer attributes over uuid", () => {
  assert.equal(
    variantVolumeLabel({
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      name: "Storage variant",
      sku: "SKU-256",
      displayAttributes: [
        { attribute: "Color", value: "Black" },
        { attribute: "Storage", value: "256GB" },
      ],
    }),
    "Black / 256GB",
  );
});

test("UI copy communicates variant override over product fallback", () => {
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "a", isActive: true, amountMinor: 200_000_000 },
      { variantId: "b", isActive: true, amountMinor: 230_000_000 },
    ],
  });
  const notes = volumePricingContextNotes({
    shape,
    view: "variant",
    hasVariantSchedules: true,
    hasProductSchedule: true,
  });
  assert.equal(notes.includes(VOLUME_PRICING_VARIANT_OVERRIDE_NOTE), true);
  assert.equal(notes.includes(VOLUME_PRICING_DIFFERENT_RETAIL_NOTE), true);
});

test("legacy risk view is keep and does not auto-convert existing fixed tiers", () => {
  const tiers = [
    { minQuantity: 10, tierType: "fixed_unit" as const, unitPrice: 1_900_000, discountPercent: null },
  ];
  const shape = classifyVolumePriceShape({
    pricingModel: "variants",
    variants: [
      { variantId: "a", isActive: true, amountMinor: 200_000_000 },
      { variantId: "b", isActive: true, amountMinor: 230_000_000 },
    ],
    productLevelTiers: tiers,
  });
  assert.equal(initialVolumePricingView({ shape, hasVariantSchedules: false }), "keep");
  assert.equal(tiers[0]?.tierType, "fixed_unit");
  assert.equal(tiers[0]?.unitPrice, 1_900_000);
});
