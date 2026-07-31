import assert from "node:assert/strict";
import { test } from "node:test";
import {
  calculateProductPublishReadiness,
  isLeafCategoryId,
  isSellableVariant,
} from "./product-publish-readiness";

const categories = [
  { id: "dept-cat", parentId: null },
  { id: "leaf-cat", parentId: "dept-cat" },
];

test("calculateProductPublishReadiness marks simple product ready when requirements met", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: isLeafCategoryId("leaf-cat", categories),
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
  assert.equal(result.path, "simple");
  assert.equal(result.missing.length, 0);
});

test("calculateProductPublishReadiness flags missing simple price and inventory", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 0,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: false,
    variants: [],
  });

  assert.equal(result.ready, false);
  assert.deepEqual(
    result.missing.map((item) => item.id).sort(),
    ["china-shipping", "china-supplier", "simple-inventory", "simple-price"].sort(),
  );
});

test("Case 1: TZ_LOCAL with store is ready", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: true,
    variants: [],
  });

  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "tz-store" && item.met), true);
  assert.equal(result.missing.some((item) => item.id === "tz-store"), false);
});

test("Case 2: TZ_LOCAL without store marks store missing", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
  });

  assert.equal(result.ready, false);
  assert.deepEqual(
    result.missing.map((item) => item.id),
    ["tz-store"],
  );
  assert.equal(result.items.find((item) => item.id === "tz-store")?.label, "Store assigned");
});

test("Case 3: CHINA_IMPORT without store does not include store check", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "tz-store"), false);
});

test("calculateProductPublishReadiness uses variant path when sellable variants exist", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 0,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: false,
    variants: [
      {
        isActive: true,
        price: 120000,
        pricesCount: 1,
        inventoriesCount: 1,
      },
    ],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.path, "variant");
  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "simple-price"), false);
});

test("publish readiness blocks active lifecycle until requirements are complete", () => {
  const incomplete = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 0,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: false,
    variants: [],
  });

  assert.equal(incomplete.ready, false);
  assert.equal(incomplete.ready || incomplete.missing.length === 0, false);
  assert.equal(incomplete.missing.some((item) => item.id === "china-shipping"), true);

  const complete = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 250000,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(complete.ready, true);
});

test("isSellableVariant requires active price and inventory policy", () => {
  assert.equal(
    isSellableVariant({
      isActive: true,
      price: 1000,
      pricesCount: 0,
      inventoriesCount: 1,
    }),
    true,
  );
  assert.equal(
    isSellableVariant({
      isActive: false,
      price: 1000,
      pricesCount: 1,
      inventoriesCount: 1,
    }),
    false,
  );
});

test("China shipping Case 1: product with valid shipping is ready", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "china-shipping" && item.met), true);
});

test("China shipping Case 2: product without shipping marks shipping missing", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
    hasPublishableShippingOption: false,
  });

  assert.equal(result.ready, false);
  assert.deepEqual(
    result.missing.map((item) => item.id).sort(),
    ["china-shipping", "china-supplier"].sort(),
  );
});

test("China shipping Case 3: TZ product does not include shipping readiness item", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: true,
    variants: [],
  });

  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "china-shipping"), false);
});

test("China shipping Case 4: unavailable shipping option with price fails readiness", () => {
  const result = calculateProductPublishReadiness({
    catalogProductTypeId: "cpt-1",
    subcategoryId: "leaf-cat",
    catalogProductTypeSubcategoryId: "leaf-cat",
    catalogProductTypeIsActive: true,
    isLeafCategory: true,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    commerceChannelId: "channel-1",
    storeId: null,
    hasSimpleInventoryPolicy: true,
    variants: [],
    hasPublishableShippingOption: false,
  });

  assert.equal(result.ready, false);
  assert.equal(result.missing.some((item) => item.id === "china-shipping"), true);
});
