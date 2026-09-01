import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mapServerCart,
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
        estimated_min_days: 7,
        estimated_max_days: 12,
        shipping_method: "air",
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
  assert.equal(line.estimatedDeliveryDays, "7–12");
  assert.equal(line.shippingMethod, "air_freight");
});

test("mapServerCartItems prefers display_attributes over nested attribute_values", () => {
  const cart: ServerCart = {
    id: "cart-1",
    items: [
      {
        id: "item-attrs",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-1",
        quantity: 1,
        unit_price: 25000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "attr-phone",
          name: "Attr Phone",
          commerce_channel_code: "CHINA_IMPORT",
          shipping_prices: { air: 9000, sea: 6000 },
        },
        variant: {
          id: "variant-1",
          sku: "ATTR-BLK",
          name: "Black",
          display_attributes: [{ attribute: "Color", value: "Black" }],
          attribute_values: [
            {
              attribute: { name: "Color", slug: "color" },
              value: "Red",
            },
          ],
        },
      },
    ],
  };

  const [line] = mapServerCartItems(cart);
  assert.deepEqual(line.selectedAttributes, [
    { name: "Color", value: "Black", slug: null },
  ]);
  assert.equal(line.configurationLabel, "Black");
});

test("mapServerCartItems keeps distinct selected-variant images on two lines of the same product", () => {
  const blackImage = "https://cdn.example/skirts/black-s.jpg";
  const redImage = "https://cdn.example/skirts/red-xxl.jpg";
  const productPrimary = "https://cdn.example/skirts/product-main.jpg";

  const cart: ServerCart = {
    id: "cart-skirts",
    items: [
      {
        id: "line-black-s",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-black-s",
        quantity: 1,
        unit_price: 25000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "stretch-pencil-skirts",
          name: "STRETCH PENCIL SKIRTS",
          primary_image: { id: "black-img", url: blackImage },
        },
        variant: {
          id: "variant-black-s",
          sku: "COT-TZ-ZIONMODE-7UVAFE-BLACK-S",
          name: "Black S",
          primary_image: { id: "black-img", url: blackImage },
        },
      },
      {
        id: "line-red-xxl",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-red-xxl",
        quantity: 1,
        unit_price: 25000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "stretch-pencil-skirts",
          name: "STRETCH PENCIL SKIRTS",
          primary_image: { id: "red-img", url: redImage },
        },
        variant: {
          id: "variant-red-xxl",
          sku: "COT-TZ-ZIONMODE-7UVAFE-RED-XXL",
          name: "Red XXL",
          primary_image: { id: "red-img", url: redImage },
        },
      },
    ],
  };

  const [blackLine, redLine] = mapServerCartItems(cart);

  assert.equal(blackLine.image.url, blackImage);
  assert.equal(redLine.image.url, redImage);
  assert.notEqual(blackLine.image.url, redLine.image.url);
  assert.notEqual(blackLine.image.url, productPrimary);
  assert.notEqual(redLine.image.url, productPrimary);
});

test("mapServerCartItems falls back to product primary when variant media is missing", () => {
  const productPrimary = "https://cdn.example/skirts/product-main.jpg";

  const cart: ServerCart = {
    id: "cart-fallback",
    items: [
      {
        id: "line-no-variant-media",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-plain",
        quantity: 1,
        unit_price: 18000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "plain-skirt",
          name: "Plain Skirt",
          primary_image: { id: "product-img", url: productPrimary },
        },
        variant: {
          id: "variant-plain",
          sku: "PLAIN-S",
          name: "S",
        },
      },
    ],
  };

  const [line] = mapServerCartItems(cart);
  assert.equal(line.image.url, productPrimary);
});

test("mapServerCartItems maps server volume_pricing and compare-at without inventing payable price", () => {
  const cart: ServerCart = {
    id: "cart-1",
    items: [
      {
        id: "item-1",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "019f7a6e-4d46-7376-aca4-aaaaaaaaaaaa",
        quantity: 3,
        unit_price: "8000.00",
        price_snapshot: "8000.00",
        product: {
          id: BASE_PRODUCT_ID,
          slug: "blouse",
          name: "Blouse",
          commerce_channel_code: "TZ_LOCAL",
        },
        volume_pricing: {
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
          ],
        },
      },
    ],
  };

  const [mapped] = mapServerCartItems(cart);
  assert.equal(mapped.unitPrice, 8000);
  assert.equal(mapped.compareAtUnitPrice, 10000);
  assert.equal(mapped.volumePricing?.eligible_quantity, 10);
  assert.equal(mapped.volumePricing?.quantity_to_next_tier, 40);
  assert.equal(mapped.volumePricing?.resolved_unit_price, "8000.00");
  assert.equal(mapped.volumePricing?.savings_total, "6000.00");
});

test("mapServerCart maps purchase_quantity lines and one blocker per product", () => {
  const cart: ServerCart = {
    id: "cart-1",
    purchase_quantity_blockers: [
      {
        product_id: BASE_PRODUCT_ID,
        minimum_quantity: 6,
        increment: 3,
        eligible_quantity: 4,
        minimum_satisfied: false,
        increment_satisfied: true,
        quantity_to_minimum: 2,
        next_legal_quantity: 6,
        blocks_checkout: true,
      },
    ],
    items: [
      {
        id: "item-red",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-red",
        quantity: 2,
        unit_price: 10000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "phone",
          name: "Phone",
          commerce_channel_code: "TZ_LOCAL",
        },
        purchase_quantity: {
          minimum_quantity: 6,
          increment: 3,
          eligible_quantity: 4,
          aggregates_variants: true,
          minimum_satisfied: false,
          increment_satisfied: true,
          quantity_to_minimum: 2,
          next_legal_quantity: 6,
          construction_complete: false,
          blocks_checkout: true,
        },
      },
      {
        id: "item-blue",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-blue",
        quantity: 2,
        unit_price: 10000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "phone",
          name: "Phone",
          commerce_channel_code: "TZ_LOCAL",
        },
        purchase_quantity: {
          minimum_quantity: 6,
          increment: 3,
          eligible_quantity: 4,
          aggregates_variants: true,
          minimum_satisfied: false,
          increment_satisfied: true,
          quantity_to_minimum: 2,
          next_legal_quantity: 6,
          construction_complete: false,
          blocks_checkout: true,
        },
      },
    ],
  };

  const mapped = mapServerCart(cart);
  assert.equal(mapped.items.length, 2);
  assert.equal(mapped.items[0]?.purchaseQuantity?.eligible_quantity, 4);
  assert.equal(mapped.items[0]?.purchaseQuantity?.aggregates_variants, true);
  assert.equal(mapped.purchaseQuantityBlockers.length, 1);
  assert.equal(mapped.purchaseQuantityBlockers[0]?.product_id, BASE_PRODUCT_ID);
});

test("mapServerCart treats missing purchase_quantity as unrestricted", () => {
  const cart: ServerCart = {
    id: "cart-1",
    items: [
      {
        id: "item-1",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: null,
        quantity: 1,
        unit_price: 10000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "plain",
          name: "Plain",
          commerce_channel_code: "CHINA_IMPORT",
        },
      },
    ],
  };

  const mapped = mapServerCart(cart);
  assert.equal(mapped.items[0]?.purchaseQuantity ?? null, null);
  assert.deepEqual(mapped.purchaseQuantityBlockers, []);
});

test("cart line catalogProductId matches blocker product_id for grouping", () => {
  const cart: ServerCart = {
    id: "cart-1",
    purchase_quantity_blockers: [
      {
        product_id: BASE_PRODUCT_ID,
        minimum_quantity: 6,
        increment: null,
        eligible_quantity: 2,
        minimum_satisfied: false,
        increment_satisfied: true,
        quantity_to_minimum: 4,
        next_legal_quantity: 6,
        blocks_checkout: true,
      },
    ],
    items: [
      {
        id: "item-1",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: "variant-red",
        quantity: 2,
        unit_price: 10000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "phone",
          name: "Phone",
          commerce_channel_code: "CHINA_IMPORT",
        },
      },
    ],
  };

  const mapped = mapServerCart(cart);
  assert.equal(mapped.items[0]?.catalogProductId, BASE_PRODUCT_ID);
  assert.equal(mapped.purchaseQuantityBlockers[0]?.product_id, mapped.items[0]?.catalogProductId);
});

test("malformed purchase_quantity degrades without breaking the cart", () => {
  const cart: ServerCart = {
    id: "cart-1",
    purchase_quantity_blockers: [
      { product_id: BASE_PRODUCT_ID, minimum_quantity: "6.5" },
      {
        product_id: "other-product",
        minimum_quantity: 8,
        increment: null,
        eligible_quantity: 1,
        minimum_satisfied: false,
        increment_satisfied: true,
        quantity_to_minimum: 7,
        next_legal_quantity: 8,
        blocks_checkout: true,
      },
    ],
    items: [
      {
        id: "item-1",
        product_id: BASE_PRODUCT_ID,
        product_variant_id: null,
        quantity: 2,
        unit_price: 10000,
        product: {
          id: BASE_PRODUCT_ID,
          slug: "plain",
          name: "Plain",
          commerce_channel_code: "TZ_LOCAL",
        },
        purchase_quantity: { minimum_quantity: 6 },
      },
    ],
  };

  const mapped = mapServerCart(cart);
  assert.equal(mapped.items.length, 1);
  assert.equal(mapped.items[0]?.purchaseQuantity ?? null, null);
  assert.equal(mapped.purchaseQuantityBlockers.length, 1);
  assert.equal(mapped.purchaseQuantityBlockers[0]?.product_id, "other-product");
});
