import assert from "node:assert/strict";
import { test } from "node:test";
import type { CartLineItem } from "@/lib/types/cart";
import type { VolumePricing } from "@/lib/pricing/volume-pricing";
import {
  cartBulkHeadline,
  groupNextTierUnlockMessage,
  hasMixedResolvedUnitPrices,
  resolveCartGroupBulkPresentation,
  siblingsAgreeOnNextTier,
} from "./cart-product-bulk-banner";

const formatMoney = (amount: number) => `TSh ${amount}`;

function volume(overrides: Partial<VolumePricing> = {}): VolumePricing {
  return {
    eligible_quantity: 10,
    aggregates_variants: true,
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
    quantity_to_next_tier: 40,
    base_unit_price: "10000.00",
    resolved_unit_price: "8000.00",
    savings_per_unit: "2000.00",
    savings_total: "6000.00",
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

function line(overrides: Partial<CartLineItem> & { volumePricing?: VolumePricing | null }): CartLineItem {
  return {
    id: "line-1",
    productId: 1,
    catalogProductId: "blouse-a",
    slug: "blouse",
    name: "Blouse",
    unitPrice: 8000,
    origin: "tz",
    categorySlug: "apparel",
    image: { id: 1, emoji: "", gradient: "", alt: "", url: "" },
    stock: 20,
    selectedSize: null,
    quantity: 3,
    addedAt: "2026-01-01T00:00:00.000Z",
    shippingMethod: "sea_freight",
    unitShippingCost: 0,
    shippingCost: 0,
    estimatedDeliveryDays: "—",
    ...overrides,
  };
}

test("A: sibling variants that share the same next threshold render one Add X more message", () => {
  const items = [
    line({ id: "red", volumePricing: volume() }),
    line({
      id: "blue",
      unitPrice: 8000,
      volumePricing: volume({ savings_total: "8000.00" }),
    }),
  ];

  const agreed = siblingsAgreeOnNextTier(items);
  assert.deepEqual(agreed, { min_quantity: 50, quantity_to_next_tier: 40 });
  assert.equal(
    groupNextTierUnlockMessage(items, formatMoney),
    "Add 40 more of this product — any variant — to unlock the next bulk tier.",
  );
  assert.equal(/each/i.test(groupNextTierUnlockMessage(items, formatMoney) ?? ""), false);
});

test("B: sibling variants with different next thresholds omit group next-tier message", () => {
  const items = [
    line({ id: "red", volumePricing: volume() }),
    line({
      id: "blue",
      volumePricing: volume({
        next_tier: {
          min_quantity: 20,
          unit_price: "9000.00",
          type: "fixed_unit",
          discount_percent: null,
          scope: "configuration",
        },
        quantity_to_next_tier: 10,
      }),
    }),
  ];

  assert.equal(siblingsAgreeOnNextTier(items), null);
  assert.equal(groupNextTierUnlockMessage(items, formatMoney), null);
});

test("C: one sibling at next tier and another at highest tier omits group next-tier message", () => {
  const items = [
    line({ id: "red", volumePricing: volume() }),
    line({
      id: "blue",
      volumePricing: volume({
        current_tier: {
          min_quantity: 50,
          unit_price: "7000.00",
          type: "fixed_unit",
          discount_percent: null,
          scope: "product",
        },
        next_tier: null,
        quantity_to_next_tier: null,
      }),
    }),
  ];

  assert.equal(siblingsAgreeOnNextTier(items), null);
  assert.equal(groupNextTierUnlockMessage(items, formatMoney), null);
});

test("D: different resolved unit prices do not produce a universal each-price claim", () => {
  const items = [
    line({
      id: "red",
      unitPrice: 8000,
      volumePricing: volume({ resolved_unit_price: "8000.00" }),
    }),
    line({
      id: "blue",
      unitPrice: 9000,
      volumePricing: volume({
        resolved_unit_price: "9000.00",
        current_tier: {
          min_quantity: 10,
          unit_price: "9000.00",
          type: "fixed_unit",
          discount_percent: null,
          scope: "configuration",
        },
      }),
    }),
  ];

  assert.equal(hasMixedResolvedUnitPrices(items), true);
  const presentation = resolveCartGroupBulkPresentation(items, formatMoney);
  assert.ok(presentation);
  assert.equal(presentation.mixedUnitPrices, true);
});

test("E: aggregate bulk tier unlocked still renders when siblings share eligibility", () => {
  const items = [
    line({ id: "red", volumePricing: volume() }),
    line({ id: "blue", volumePricing: volume({ resolved_unit_price: "9000.00" }) }),
  ];

  const presentation = resolveCartGroupBulkPresentation(items, formatMoney);
  assert.ok(presentation);
  assert.equal(presentation.unlocked, true);
  assert.equal(presentation.pricing.eligible_quantity, 10);
  assert.equal(cartBulkHeadline(presentation.pricing), "10 total pieces — bulk tier unlocked");
  assert.equal(presentation.mixedUnitPrices, true);
  assert.equal(
    presentation.nextMessage,
    "Add 40 more of this product — any variant — to unlock the next bulk tier.",
  );
});

test("missing volume_pricing on a sibling omits group next-tier message", () => {
  const items = [
    line({ id: "red", volumePricing: volume() }),
    line({ id: "blue", volumePricing: null }),
  ];

  assert.equal(siblingsAgreeOnNextTier(items), null);
  assert.equal(groupNextTierUnlockMessage(items, formatMoney), null);
});
