import assert from "node:assert/strict";
import { test } from "node:test";
import {
  calculateProductPublishReadiness,
  isLeafCategoryId,
  isSellableVariant,
  variantHasRetailPrice,
  variantHasStockPolicy,
} from "./product-publish-readiness";

const categories = [
  { id: "dept-cat", parentId: null },
  { id: "leaf-cat", parentId: "dept-cat" },
];

const baseReady = {
  catalogProductTypeId: "cpt-1",
  subcategoryId: "leaf-cat",
  catalogProductTypeSubcategoryId: "leaf-cat",
  catalogProductTypeIsActive: true,
  isLeafCategory: isLeafCategoryId("leaf-cat", categories),
  commerceChannelId: "channel-1",
  storeId: null as string | null,
  hasSimpleInventoryPolicy: false,
};

test("calculateProductPublishReadiness marks simple product ready when requirements met", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
  assert.equal(result.path, "simple");
  assert.equal(result.missing.length, 0);
  assert.equal(result.items.some((item) => item.id === "simple-inventory"), false);
});

test("Test A: CHINA_IMPORT ready without inventory policy when price supplier shipping met", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "simple-inventory"), false);
});

test("Test B: CHINA_IMPORT missing supplier blocks readiness", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    variants: [],
    supplierId: null,
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, false);
  assert.deepEqual(
    result.missing.map((item) => item.id),
    ["china-supplier"],
  );
});

test("Test C: TZ_LOCAL missing inventory policy blocks readiness", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: false,
    variants: [],
  });

  assert.equal(result.ready, false);
  assert.deepEqual(
    result.missing.map((item) => item.id),
    ["simple-inventory"],
  );
});

test("Test D: TZ_LOCAL ready when price and inventory policy met", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: true,
    variants: [],
  });

  assert.equal(result.ready, true);
});

test("simple product still requires base price greater than zero", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 0,
    pricingModel: "simple",
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: true,
    variants: [],
  });

  assert.equal(result.path, "simple");
  assert.equal(result.ready, false);
  assert.ok(result.missing.some((item) => item.id === "simple-price"));
  assert.equal(result.missing.find((item) => item.id === "simple-price")?.label, "Base price greater than zero");
});

test("variant product with price 0 and sellable variants does not require base price", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 0,
    pricingModel: "variants",
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: false,
    variants: [
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 1,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
    ],
  });

  assert.equal(result.path, "variant");
  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "simple-price"), false);
});

test("stale unfinished variants use variant messaging not simple base price", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 0,
    pricingModel: "variants",
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: false,
    variants: [
      {
        isActive: true,
        price: null,
        pricesCount: 0,
        inventoriesCount: 0,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
    ],
  });

  assert.equal(result.path, "variant");
  assert.equal(result.ready, false);
  assert.equal(result.items.some((item) => item.id === "simple-price"), false);
  assert.ok(result.missing.some((item) => item.id === "variant-retail-pricing"));
  assert.ok(result.missing.some((item) => item.id === "variant-warehouse-inventory"));
  assert.equal(
    result.missing.find((item) => item.id === "variant-retail-pricing")?.label,
    "Variant retail pricing complete",
  );
});

test("refreshed pricesCount flips readiness toward variant-ready when stock exists", () => {
  const incomplete = calculateProductPublishReadiness({
    ...baseReady,
    price: 0,
    pricingModel: "variants",
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: false,
    variants: [
      {
        isActive: true,
        price: null,
        pricesCount: 0,
        inventoriesCount: 1,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
    ],
  });
  assert.equal(incomplete.ready, false);
  assert.ok(incomplete.missing.some((item) => item.id === "variant-retail-pricing"));

  const complete = calculateProductPublishReadiness({
    ...baseReady,
    price: 0,
    pricingModel: "variants",
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: false,
    variants: [
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 1,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
    ],
  });
  assert.equal(complete.ready, true);
  assert.equal(complete.path, "variant");
});

test("TZ_LOCAL sellability uses retail price and warehouse inventory", () => {
  assert.equal(
    isSellableVariant(
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 1,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
      "TZ_LOCAL",
    ),
    true,
  );
  assert.equal(
    isSellableVariant(
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 0,
        commercialStocksCount: 1,
        hasActiveCommercialStock: true,
      },
      "TZ_LOCAL",
    ),
    false,
  );
  assert.equal(
    variantHasStockPolicy(
      {
        isActive: true,
        price: null,
        pricesCount: 0,
        inventoriesCount: 1,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
      "TZ_LOCAL",
    ),
    true,
  );
});

test("CHINA_IMPORT sellability uses retail price and commercial stock without inventoriesCount", () => {
  assert.equal(
    isSellableVariant(
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 0,
        commercialStocksCount: 1,
        hasActiveCommercialStock: true,
      },
      "CHINA_IMPORT",
    ),
    true,
  );
  assert.equal(
    isSellableVariant(
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 1,
        commercialStocksCount: 0,
        hasActiveCommercialStock: false,
      },
      "CHINA_IMPORT",
    ),
    false,
  );
  assert.equal(
    variantHasStockPolicy(
      {
        isActive: true,
        price: null,
        pricesCount: 0,
        inventoriesCount: 0,
        commercialStocksCount: 0,
        hasActiveCommercialStock: true,
      },
      "CHINA_IMPORT",
    ),
    true,
  );
});

test("CHINA_IMPORT variant readiness with commercial stock and retail prices", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 0,
    pricingModel: "variants",
    commerceChannelCode: "CHINA_IMPORT",
    variants: [
      {
        isActive: true,
        price: null,
        pricesCount: 1,
        inventoriesCount: 0,
        commercialStocksCount: 1,
        hasActiveCommercialStock: true,
      },
    ],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.path, "variant");
  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "simple-price"), false);
  assert.ok(result.items.some((item) => item.id === "variant-commercial-stock" && item.met));
});

test("Case 1: TZ_LOCAL with store is ready", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "TZ_LOCAL",
    commerceChannelId: "channel-tz",
    storeId: "store-1",
    hasSimpleInventoryPolicy: true,
    variants: [],
  });

  assert.equal(result.ready, true);
});

test("Case 2: TZ_LOCAL without store marks store missing", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
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
});

test("Case 3: CHINA_IMPORT without store does not include store check", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
  assert.equal(result.items.some((item) => item.id === "tz-store"), false);
});

test("variantHasRetailPrice treats pricesCount as VariantPrice presence", () => {
  assert.equal(
    variantHasRetailPrice({
      isActive: true,
      price: null,
      pricesCount: 1,
      inventoriesCount: 0,
      commercialStocksCount: 0,
      hasActiveCommercialStock: false,
    }),
    true,
  );
});

test("China shipping Case 1: product with valid shipping is ready", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    variants: [],
    supplierId: "supplier-1",
    hasPublishableShippingOption: true,
  });

  assert.equal(result.ready, true);
});

test("China shipping Case 2: product without shipping marks shipping missing", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
    price: 150000,
    commerceChannelCode: "CHINA_IMPORT",
    variants: [],
    hasPublishableShippingOption: false,
  });

  assert.equal(result.ready, false);
  assert.ok(result.missing.some((item) => item.id === "china-shipping"));
});

test("China shipping Case 3: TZ product does not include shipping readiness item", () => {
  const result = calculateProductPublishReadiness({
    ...baseReady,
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
