import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mapServerCartItems,
  resolveServerCartProductOrigin,
  type ServerCart,
} from "./customer-cart";

const BASE_PRODUCT_ID = "019f7a6e-4d46-7376-aca4-aed79f33519b";

test("CHINA_IMPORT resolves to china even without freight fields", () => {
  assert.equal(
    resolveServerCartProductOrigin({
      id: BASE_PRODUCT_ID,
      commerce_channel_code: "CHINA_IMPORT",
    }),
    "china",
  );
});

test("TZ_LOCAL resolves to tz even when freight fields are present", () => {
  assert.equal(
    resolveServerCartProductOrigin({
      id: BASE_PRODUCT_ID,
      commerce_channel_code: "TZ_LOCAL",
      shipping_prices: { air: 9000, sea: 5000 },
      air_shipping_price: 9000,
      sea_shipping_price: 5000,
    }),
    "tz",
  );
});

test("legacy product without channel resolves china from shipping_prices", () => {
  assert.equal(
    resolveServerCartProductOrigin({
      id: BASE_PRODUCT_ID,
      shipping_prices: { air: 12000, sea: null },
    }),
    "china",
  );
});

test("legacy product without channel resolves china from legacy freight columns", () => {
  assert.equal(
    resolveServerCartProductOrigin({
      id: BASE_PRODUCT_ID,
      air_shipping_price: 8000,
    }),
    "china",
  );
});

test("legacy product without channel and without freight resolves tz", () => {
  assert.equal(
    resolveServerCartProductOrigin({
      id: BASE_PRODUCT_ID,
    }),
    "tz",
  );
});

test("mapServerCartItems maps china line origin and shipping costs from API contract", () => {
  const cart: ServerCart = {
    id: "cart-1",
    items: [
      {
        id: "item-1",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: null,
        quantity: 1,
        unit_price: 25000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "china-phone",
          name: "China Phone",
          commerce_channel_code: "CHINA_IMPORT",
          shipping_prices: { air: 9000, sea: 6000 },
        },
      },
    ],
  };

  const [line] = mapServerCartItems(cart);
  assert.equal(line.origin, "china");
  assert.equal(line.airCost, 9000);
  assert.equal(line.seaCost, 6000);
});

test("mapServerCartItems maps tz line origin from commerce channel", () => {
  const cart: ServerCart = {
    id: "cart-1",
    items: [
      {
        id: "item-2",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: null,
        quantity: 1,
        unit_price: 18000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "tz-dress",
          name: "TZ Dress",
          commerce_channel_code: "TZ_LOCAL",
        },
      },
    ],
  };

  const [line] = mapServerCartItems(cart);
  assert.equal(line.origin, "tz");
  assert.equal(line.airCost, undefined);
  assert.equal(line.seaCost, undefined);
});
