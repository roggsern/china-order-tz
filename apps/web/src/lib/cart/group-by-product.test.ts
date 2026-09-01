import assert from "node:assert/strict";
import { test } from "node:test";
import { groupCartLinesByCatalogProduct } from "./group-by-product";
import type { CartLineItem } from "@/lib/types/cart";

function line(overrides: Partial<CartLineItem>): CartLineItem {
  return {
    id: "line-1",
    productId: 1,
    catalogProductId: "product-a",
    slug: "blouse",
    name: "Blouse",
    unitPrice: 8000,
    origin: "tz",
    categorySlug: "apparel",
    image: { id: 1, emoji: "", gradient: "", alt: "", url: "" },
    stock: 20,
    selectedSize: null,
    quantity: 1,
    addedAt: "2026-01-01T00:00:00.000Z",
    shippingMethod: "sea_freight",
    unitShippingCost: 0,
    shippingCost: 0,
    estimatedDeliveryDays: "—",
    ...overrides,
  };
}

test("cart grouping keeps same catalog product_id together", () => {
  const grouped = groupCartLinesByCatalogProduct([
    line({ id: "red", catalogProductId: "blouse-a", configurationLabel: "Red XL", quantity: 3 }),
    line({ id: "other", catalogProductId: "skirt-b", name: "Skirt", quantity: 2 }),
    line({ id: "blue", catalogProductId: "blouse-a", configurationLabel: "Blue M", quantity: 4 }),
  ]);

  assert.equal(grouped.length, 2);
  assert.deepEqual(
    grouped[0]?.map((item) => item.id),
    ["red", "blue"],
  );
  assert.deepEqual(
    grouped[1]?.map((item) => item.id),
    ["other"],
  );
});

test("different product_ids never share a bulk group", () => {
  const grouped = groupCartLinesByCatalogProduct([
    line({ id: "a", catalogProductId: "p1", quantity: 6 }),
    line({ id: "b", catalogProductId: "p2", quantity: 6 }),
  ]);

  assert.equal(grouped.length, 2);
  assert.equal(grouped[0]?.length, 1);
  assert.equal(grouped[1]?.length, 1);
});
