import assert from "node:assert/strict";
import { test } from "node:test";
import {
  MIXED_VARIANTS_BULK_NOTE,
  cartBulkHeadline,
  cartNextTierUnlockMessage,
  mapVolumePricing,
  nextTierUnlockMessage,
  remainingToNextTier,
  volumePricingUnlocked,
  type VolumePricing,
} from "./volume-pricing";

const formatMoney = (amount: number) => `TSh ${amount.toLocaleString("en-US")}`;

function samplePayload(overrides: Record<string, unknown> = {}) {
  return {
    eligible_quantity: 7,
    aggregates_variants: false,
    current_tier: null,
    next_tier: {
      min_quantity: 10,
      unit_price: "8000.00",
      type: "fixed_unit",
      discount_percent: null,
      scope: "product",
    },
    quantity_to_next_tier: 3,
    base_unit_price: "10000.00",
    resolved_unit_price: "10000.00",
    savings_per_unit: "0.00",
    savings_total: "0.00",
    currency: "TZS",
    tiers: [
      {
        min_quantity: 10,
        unit_price: "8000.00",
        type: "fixed_unit",
        discount_percent: null,
        scope: "product",
      },
      {
        min_quantity: 50,
        unit_price: "7000.00",
        type: "fixed_unit",
        discount_percent: null,
        scope: "product",
      },
    ],
    ...overrides,
  };
}

test("mapVolumePricing preserves server tier unit prices without client math", () => {
  const mapped = mapVolumePricing(
    samplePayload({
      tiers: [
        {
          min_quantity: 10,
          unit_price: "9000.00",
          type: "percent_off",
          discount_percent: "10.00",
          scope: "product",
        },
      ],
    }),
  );

  assert.ok(mapped);
  assert.equal(mapped.tiers[0]?.unit_price, "9000.00");
  assert.equal(mapped.tiers[0]?.type, "percent_off");
  assert.notEqual(mapped.tiers[0]?.unit_price, String(10000 * 0.9));
});

test("mapVolumePricing returns null when tiers are missing", () => {
  assert.equal(mapVolumePricing({ eligible_quantity: 1, base_unit_price: "1", resolved_unit_price: "1" }), null);
});

test("PDP next-tier copy uses server quantity_to_next_tier and next unit price", () => {
  const pricing = mapVolumePricing(samplePayload()) as VolumePricing;
  assert.equal(remainingToNextTier(pricing), 3);
  assert.equal(nextTierUnlockMessage(pricing, formatMoney), "Add 3 more to unlock TSh 8,000 each");
  assert.equal(volumePricingUnlocked(pricing), false);
});

test("unlocked copy and next tier come from server current/next fields", () => {
  const pricing = mapVolumePricing(
    samplePayload({
      eligible_quantity: 12,
      current_tier: {
        min_quantity: 10,
        unit_price: "8000.00",
        type: "fixed_unit",
        discount_percent: null,
        scope: "product",
      },
      next_tier: {
        min_quantity: 50,
        unit_price: "7000.00",
        type: "fixed_unit",
        discount_percent: null,
        scope: "product",
      },
      quantity_to_next_tier: 38,
      resolved_unit_price: "8000.00",
      savings_per_unit: "2000.00",
      savings_total: "24000.00",
    }),
  ) as VolumePricing;

  assert.equal(volumePricingUnlocked(pricing), true);
  assert.equal(cartBulkHeadline(pricing), "12 total pieces — bulk tier unlocked");
  assert.equal(
    nextTierUnlockMessage(pricing, formatMoney),
    "Add 38 more to unlock TSh 7,000 each",
  );
});

test("highest tier omits next-tier copy", () => {
  const pricing = mapVolumePricing(
    samplePayload({
      eligible_quantity: 50,
      current_tier: {
        min_quantity: 50,
        unit_price: "7000.00",
        type: "fixed_unit",
        discount_percent: null,
        scope: "product",
      },
      next_tier: null,
      quantity_to_next_tier: null,
      resolved_unit_price: "7000.00",
    }),
  ) as VolumePricing;

  assert.equal(nextTierUnlockMessage(pricing, formatMoney), null);
  assert.equal(remainingToNextTier(pricing), null);
});

test("configurable mixed-variant cart copy uses server remaining quantity", () => {
  const pricing = mapVolumePricing(
    samplePayload({
      aggregates_variants: true,
      eligible_quantity: 8,
      quantity_to_next_tier: 2,
    }),
  ) as VolumePricing;

  assert.equal(
    cartNextTierUnlockMessage(pricing, true, formatMoney),
    "Add 2 more of this product — any variant — to unlock the next bulk tier.",
  );
  assert.equal(MIXED_VARIANTS_BULK_NOTE.includes("count together"), true);
});
