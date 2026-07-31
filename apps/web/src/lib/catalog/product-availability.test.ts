import assert from "node:assert/strict";
import { test } from "node:test";
import { mapApiProductCardToCatalogProduct } from "./map-api-product";
import {
  isProductCardPurchaseDisabled,
  isProductPurchaseUnavailable,
  resolveProductCardAvailabilityOverlay,
  resolvePurchaseDisabledLabel,
} from "./product-availability";

test("isProductPurchaseUnavailable is true when isPurchasable is false", () => {
  assert.equal(isProductPurchaseUnavailable({ isPurchasable: false }), true);
  assert.equal(isProductPurchaseUnavailable({ isPurchasable: true }), false);
  assert.equal(isProductPurchaseUnavailable({ availabilityStatus: "unavailable" }), true);
  assert.equal(isProductPurchaseUnavailable({}), false);
});

test("resolvePurchaseDisabledLabel distinguishes policy vs stock states", () => {
  assert.equal(
    resolvePurchaseDisabledLabel({
      disabled: true,
      purchaseUnavailable: true,
      variant: "detail",
    }),
    "Currently unavailable",
  );

  assert.equal(
    resolvePurchaseDisabledLabel({
      disabled: true,
      availabilityStatus: "unavailable",
      variant: "card",
    }),
    "Currently unavailable",
  );

  assert.equal(
    resolvePurchaseDisabledLabel({
      disabled: true,
      purchaseUnavailable: false,
      variant: "detail",
      configurationId: null,
    }),
    "Select options",
  );

  assert.equal(
    resolvePurchaseDisabledLabel({
      disabled: true,
      availabilityStatus: "out_of_stock",
      variant: "card",
    }),
    "Out of Stock",
  );
});

test("resolveProductCardAvailabilityOverlay uses availability_status", () => {
  assert.equal(
    resolveProductCardAvailabilityOverlay({
      availabilityStatus: "unavailable",
      stock: 0,
    }),
    "Currently unavailable",
  );

  assert.equal(
    resolveProductCardAvailabilityOverlay({
      availabilityStatus: "out_of_stock",
      stock: 0,
    }),
    "Out of Stock",
  );

  assert.equal(
    resolveProductCardAvailabilityOverlay({
      availabilityStatus: "available",
      stock: 5,
    }),
    null,
  );
});

test("isProductCardPurchaseDisabled respects availability_status", () => {
  assert.equal(
    isProductCardPurchaseDisabled({ availabilityStatus: "unavailable", stock: 5 }),
    true,
  );
  assert.equal(
    isProductCardPurchaseDisabled({ availabilityStatus: "out_of_stock", stock: 0 }),
    true,
  );
  assert.equal(
    isProductCardPurchaseDisabled({ availabilityStatus: "available", stock: 4 }),
    false,
  );
});

test("card mapping preserves listing availability fields", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    id: "019f99db-3b6b-720d-b5d6-f4666a6444ed",
    slug: "iphone-16-pro",
    name: "iPhone 16 Pro",
    short_description: "Demo",
    price: "3499000",
    compare_at_price: null,
    is_featured: false,
    primary_image: null,
    category: null,
    brand: null,
    average_rating: 0,
    review_count: 0,
    is_purchasable: false,
    availability_status: "unavailable",
    unavailability_reason: "missing_inventory_policy",
  });

  assert.equal(mapped.isPurchasable, false);
  assert.equal(mapped.availabilityStatus, "unavailable");
  assert.equal(mapped.unavailabilityReason, "missing_inventory_policy");
});

test("available purchasable card mapping unchanged aside from availability fields", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    id: "019f99db-aaaa-720d-b5d6-f4666a6444ed",
    slug: "available-phone",
    name: "Available Phone",
    short_description: "Phone",
    price: "1000",
    compare_at_price: null,
    is_featured: false,
    primary_image: null,
    category: null,
    brand: null,
    average_rating: 4.5,
    review_count: 2,
    stock: 8,
    is_purchasable: true,
    availability_status: "available",
  });

  assert.equal(mapped.stock, 8);
  assert.equal(mapped.isPurchasable, true);
  assert.equal(mapped.availabilityStatus, "available");
  assert.equal(isProductCardPurchaseDisabled(mapped), false);
  assert.equal(resolveProductCardAvailabilityOverlay(mapped), null);
});
