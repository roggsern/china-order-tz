import assert from "node:assert/strict";
import { test } from "node:test";
import { purchaseQuantityWriteFields } from "./purchase-quantity-rules";
import {
  VOLUME_PRICING_EDITOR_TITLE,
  applyVolumePricingEnabledChange,
  canonicalVolumePricingValidationError,
  collectVariantVolumeWrites,
  emptyVariantVolumeDraft,
  firstVolumePricingFormError,
  inferredVolumeRangeLabels,
  mapProductLevelPriceTiers,
  productVolumeWriteShouldInclude,
  starterVolumePricingTier,
  variantVolumeDraftFromTiers,
  volumePricingWriteFields,
} from "./volume-pricing-tiers";
import type { ProductPriceTierDraft } from "@/lib/types/catalog";

const existingTiers: ProductPriceTierDraft[] = [
  { minQuantity: 10, tierType: "fixed_unit", unitPrice: 8000, discountPercent: null },
  { minQuantity: 50, tierType: "fixed_unit", unitPrice: 6000, discountPercent: null },
  { minQuantity: 100, tierType: "fixed_unit", unitPrice: 5000, discountPercent: null },
];

function canSaveVolumePricing(enabled: boolean, tiers: ProductPriceTierDraft[]): boolean {
  return firstVolumePricingFormError(enabled, tiers) === undefined;
}

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

test("existing product with tiers can disable Bulk / Volume Pricing", () => {
  const next = applyVolumePricingEnabledChange({
    nextEnabled: false,
    tiers: existingTiers,
    basePrice: 10000,
  });

  assert.equal(next.enabled, false);
  assert.equal(next.tiers, existingTiers);
  assert.equal(firstVolumePricingFormError(next.enabled, next.tiers), undefined);
  assert.equal(canSaveVolumePricing(next.enabled, next.tiers), true);
  assert.deepEqual(
    volumePricingWriteFields({
      loaded: true,
      enabled: next.enabled,
      tiers: next.tiers,
    }).price_tiers,
    [],
  );
});

test("disabled editor with zero tiers has no validation error and can save", () => {
  assert.equal(firstVolumePricingFormError(false, []), undefined);
  assert.equal(canSaveVolumePricing(false, []), true);
  assert.deepEqual(
    volumePricingWriteFields({ loaded: true, enabled: false, tiers: [] }).price_tiers,
    [],
  );
});

test("disabled save explicitly clears product-level price tiers only", () => {
  const productLevelClear = volumePricingWriteFields({
    loaded: true,
    enabled: false,
    tiers: existingTiers,
  });
  assert.deepEqual(productLevelClear.price_tiers, []);
  assert.equal(
    JSON.stringify(productLevelClear).includes("configuration"),
    false,
  );

  const mapped = mapProductLevelPriceTiers([
    {
      min_quantity: 10,
      unit_price: "8000.00",
      tier_type: "fixed_unit",
      configuration_id: null,
    },
    {
      min_quantity: 5,
      unit_price: "7000.00",
      tier_type: "fixed_unit",
      configuration_id: "cfg-red",
    },
  ]);
  assert.equal(mapped.length, 1);
  assert.equal(mapped[0]?.minQuantity, 10);
});

test("enabled editor with zero tiers remains invalid", () => {
  assert.equal(
    firstVolumePricingFormError(true, []),
    "Add at least one bulk pricing tier, or disable bulk / volume pricing.",
  );
  assert.equal(canSaveVolumePricing(true, []), false);
});

test("removing the final tier then unchecking Enable clears the validation error", () => {
  let enabled = true;
  let tiers = existingTiers;

  tiers = [];
  assert.equal(
    firstVolumePricingFormError(enabled, tiers),
    "Add at least one bulk pricing tier, or disable bulk / volume pricing.",
  );

  const next = applyVolumePricingEnabledChange({
    nextEnabled: false,
    tiers,
    basePrice: 10000,
  });
  enabled = next.enabled;
  tiers = next.tiers;

  assert.equal(enabled, false);
  assert.deepEqual(tiers, []);
  assert.equal(firstVolumePricingFormError(enabled, tiers), undefined);
  assert.equal(canSaveVolumePricing(enabled, tiers), true);
});

test("re-enabling restores preserved drafts or inserts a starter tier", () => {
  const preserved = applyVolumePricingEnabledChange({
    nextEnabled: false,
    tiers: existingTiers,
    basePrice: 10000,
  });
  const restored = applyVolumePricingEnabledChange({
    nextEnabled: true,
    tiers: preserved.tiers,
    basePrice: 10000,
  });
  assert.equal(restored.enabled, true);
  assert.equal(restored.tiers, existingTiers);
  assert.equal(canSaveVolumePricing(restored.enabled, restored.tiers), true);

  const emptied = applyVolumePricingEnabledChange({
    nextEnabled: false,
    tiers: [],
    basePrice: 10000,
  });
  const started = applyVolumePricingEnabledChange({
    nextEnabled: true,
    tiers: emptied.tiers,
    basePrice: 10000,
  });
  assert.equal(started.enabled, true);
  assert.deepEqual(started.tiers, [starterVolumePricingTier(10000)]);
  assert.equal(started.tiers[0]?.minQuantity, 10);
  assert.equal(canSaveVolumePricing(started.enabled, started.tiers), true);
});

test("disabling volume pricing does not change purchase quantity rules", () => {
  const quantity = purchaseQuantityWriteFields(6, 3);
  const volume = volumePricingWriteFields({
    loaded: true,
    enabled: false,
    tiers: existingTiers,
  });
  const payload = { ...quantity, ...volume };

  assert.equal(payload.minimum_order_quantity, 6);
  assert.equal(payload.order_increment, 3);
  assert.deepEqual(payload.price_tiers, []);
  assert.deepEqual(purchaseQuantityWriteFields(6, 3), quantity);
});

test("disable toggle does not emit a second empty-tiers update that would restore enabled", () => {
  const snapshot = { enabled: true, tiers: [] as ProductPriceTierDraft[] };
  const next = applyVolumePricingEnabledChange({
    nextEnabled: false,
    tiers: snapshot.tiers,
    basePrice: 10000,
  });

  assert.equal(next.tiers, snapshot.tiers);

  const staleOverwrite = { ...snapshot, tiers: [] };
  assert.equal(staleOverwrite.enabled, true);

  const applied = { ...snapshot, enabled: next.enabled };
  assert.equal(applied.enabled, false);
});

test("inferred ranges describe 10–49, 50–99, and 100+ without inventing max_quantity", () => {
  const labels = inferredVolumeRangeLabels(
    existingTiers,
    (amount) => `TZS ${amount.toLocaleString("en-US")}`,
  );

  assert.deepEqual(labels, [
    "10–49 uses TZS 8,000",
    "50–99 uses TZS 6,000",
    "100+ uses TZS 5,000",
  ]);
});

const variantATiers: ProductPriceTierDraft[] = [
  { minQuantity: 10, tierType: "fixed_unit", unitPrice: 1_900_000, discountPercent: null },
];
const variantBTiers: ProductPriceTierDraft[] = [
  { minQuantity: 10, tierType: "fixed_unit", unitPrice: 2_150_000, discountPercent: null },
];

test("variant A dirty save emits A only", () => {
  const initialA = variantVolumeDraftFromTiers(variantATiers);
  const initialB = variantVolumeDraftFromTiers(variantBTiers);
  const writes = collectVariantVolumeWrites({
    view: "variant",
    loaded: true,
    relevantVariantIds: ["a", "b"],
    drafts: {
      a: variantVolumeDraftFromTiers([
        { minQuantity: 10, tierType: "fixed_unit", unitPrice: 1_800_000, discountPercent: null },
      ]),
      b: initialB,
    },
    initial: { a: initialA, b: initialB },
  });

  assert.equal(writes.length, 1);
  assert.equal(writes[0]?.configurationId, "a");
  assert.deepEqual(writes[0]?.priceTiers, [
    { min_quantity: 10, tier_type: "fixed_unit", unit_price: 1_800_000, discount_percent: null },
  ]);
});

test("variant B dirty save emits B only", () => {
  const initialA = variantVolumeDraftFromTiers(variantATiers);
  const initialB = variantVolumeDraftFromTiers(variantBTiers);
  const writes = collectVariantVolumeWrites({
    view: "variant",
    loaded: true,
    relevantVariantIds: ["a", "b"],
    drafts: {
      a: initialA,
      b: variantVolumeDraftFromTiers([
        { minQuantity: 10, tierType: "fixed_unit", unitPrice: 2_000_000, discountPercent: null },
      ]),
    },
    initial: { a: initialA, b: initialB },
  });

  assert.equal(writes.length, 1);
  assert.equal(writes[0]?.configurationId, "b");
});

test("clearing A emits empty tiers for A and does not include B", () => {
  const initialA = variantVolumeDraftFromTiers(variantATiers);
  const initialB = variantVolumeDraftFromTiers(variantBTiers);
  const writes = collectVariantVolumeWrites({
    view: "variant",
    loaded: true,
    relevantVariantIds: ["a", "b"],
    drafts: {
      a: emptyVariantVolumeDraft(),
      b: initialB,
    },
    initial: { a: initialA, b: initialB },
  });

  assert.deepEqual(writes, [{ configurationId: "a", priceTiers: [] }]);
});

test("product-level edits do not emit variant-scoped writes", () => {
  const drafts = {
    a: variantVolumeDraftFromTiers(variantATiers),
    b: variantVolumeDraftFromTiers(variantBTiers),
  };
  assert.deepEqual(
    collectVariantVolumeWrites({
      view: "product",
      loaded: true,
      relevantVariantIds: ["a", "b"],
      drafts,
      initial: drafts,
    }),
    [],
  );
});

test("variant-level edits omit product-level writes unless explicitly requested", () => {
  assert.equal(productVolumeWriteShouldInclude({ view: "variant", writeProduct: false }), false);
  assert.equal(productVolumeWriteShouldInclude({ view: "product", writeProduct: true }), true);
});

test("keep view does not silently delete existing product-level fixed_unit tiers", () => {
  assert.equal(productVolumeWriteShouldInclude({ view: "keep", writeProduct: false }), false);
  assert.equal(productVolumeWriteShouldInclude({ view: "keep", writeProduct: true }), false);
  assert.equal(
    canonicalVolumePricingValidationError({
      view: "keep",
      writeProduct: false,
      productEnabled: true,
      productTiers: existingTiers,
      relevantVariantIds: ["a", "b"],
      variantDrafts: {},
    }),
    undefined,
  );
});

test("explicit product-level clear still writes empty product tiers only", () => {
  assert.deepEqual(
    volumePricingWriteFields({
      loaded: true,
      enabled: false,
      tiers: existingTiers,
    }).price_tiers,
    [],
  );
  assert.deepEqual(
    collectVariantVolumeWrites({
      view: "product",
      loaded: true,
      relevantVariantIds: ["a", "b"],
      drafts: {
        a: variantVolumeDraftFromTiers(variantATiers),
        b: variantVolumeDraftFromTiers(variantBTiers),
      },
      initial: {
        a: variantVolumeDraftFromTiers(variantATiers),
        b: variantVolumeDraftFromTiers(variantBTiers),
      },
    }),
    [],
  );
});

test("switching modes does not emit destructive writes for the other scope", () => {
  const drafts = {
    a: variantVolumeDraftFromTiers(variantATiers),
    b: variantVolumeDraftFromTiers(variantBTiers),
  };
  assert.deepEqual(
    collectVariantVolumeWrites({
      view: "product",
      loaded: true,
      relevantVariantIds: ["a", "b"],
      drafts,
      initial: drafts,
    }),
    [],
  );
  assert.equal(productVolumeWriteShouldInclude({ view: "variant", writeProduct: false }), false);
});
