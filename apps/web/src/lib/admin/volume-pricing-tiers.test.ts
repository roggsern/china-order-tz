import assert from "node:assert/strict";
import { test } from "node:test";
import {
  VOLUME_PRICING_EDITOR_TITLE,
  firstVolumePricingFormError,
  inferredVolumeRangeLabels,
  mapProductLevelPriceTiers,
  volumePricingWriteFields,
} from "./volume-pricing-tiers";

test("canonical editor title is Bulk / Volume Pricing", () => {
  assert.equal(VOLUME_PRICING_EDITOR_TITLE, "Bulk / Volume Pricing");
});

test("mapProductLevelPriceTiers keeps product-level rows and sorts by min quantity", () => {
  const mapped = mapProductLevelPriceTiers([
    {
      configuration_id: "cfg-1",
      min_quantity: 5,
      unit_price: "7000.00",
      tier_type: "fixed_unit",
    },
    {
      min_quantity: 50,
      unit_price: "6000.00",
      tier_type: "fixed_unit",
    },
    {
      configuration_id: null,
      min_quantity: 10,
      unit_price: "8000.00",
      tier_type: "fixed_unit",
    },
  ]);

  assert.equal(mapped.length, 2);
  assert.equal(mapped[0]?.minQuantity, 10);
  assert.equal(mapped[0]?.unitPrice, 8000);
  assert.equal(mapped[1]?.minQuantity, 50);
  assert.equal(mapped[1]?.unitPrice, 6000);
});

test("volumePricingWriteFields omits price_tiers until the editor has loaded", () => {
  const untouched = volumePricingWriteFields({
    loaded: false,
    enabled: false,
    tiers: [],
  });
  assert.equal("price_tiers" in untouched, false);

  const explicitClear = volumePricingWriteFields({
    loaded: true,
    enabled: false,
    tiers: [{ minQuantity: 10, tierType: "fixed_unit", unitPrice: 8000, discountPercent: null }],
  });
  assert.deepEqual(explicitClear.price_tiers, []);

  const saved = volumePricingWriteFields({
    loaded: true,
    enabled: true,
    tiers: [
      { minQuantity: 50, tierType: "fixed_unit", unitPrice: 6000, discountPercent: null },
      { minQuantity: 10, tierType: "fixed_unit", unitPrice: 8000, discountPercent: null },
    ],
  });
  assert.deepEqual(saved.price_tiers, [
    { min_quantity: 10, tier_type: "fixed_unit", unit_price: 8000, discount_percent: null },
    { min_quantity: 50, tier_type: "fixed_unit", unit_price: 6000, discount_percent: null },
  ]);
});

test("volumePricingFormErrors rejects duplicates, missing prices, and empty enabled editors", () => {
  assert.equal(
    firstVolumePricingFormError(true, []),
    "Add at least one bulk pricing tier, or disable bulk / volume pricing.",
  );
  assert.match(
    firstVolumePricingFormError(true, [
      { minQuantity: 10, tierType: "fixed_unit", unitPrice: 8000, discountPercent: null },
      { minQuantity: 10, tierType: "fixed_unit", unitPrice: 7000, discountPercent: null },
    ]) ?? "",
    /unique minimum quantity/,
  );
  assert.match(
    firstVolumePricingFormError(true, [
      { minQuantity: 10, tierType: "fixed_unit", unitPrice: null, discountPercent: null },
    ]) ?? "",
    /Unit price/,
  );
  assert.match(
    firstVolumePricingFormError(true, [
      { minQuantity: 10, tierType: "percent_off", unitPrice: null, discountPercent: 120 },
    ]) ?? "",
    /Discount percent/,
  );
  assert.equal(firstVolumePricingFormError(false, []), undefined);
});

test("inferred ranges describe 10–49 and 50+ without inventing max_quantity", () => {
  const labels = inferredVolumeRangeLabels(
    [
      { minQuantity: 10, tierType: "fixed_unit", unitPrice: 8000, discountPercent: null },
      { minQuantity: 50, tierType: "fixed_unit", unitPrice: 6000, discountPercent: null },
    ],
    (amount) => `TZS ${amount.toLocaleString("en-US")}`,
  );

  assert.deepEqual(labels, ["10–49 uses TZS 8,000", "50+ uses TZS 6,000"]);
});
