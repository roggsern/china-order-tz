import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CartLineItem } from "@/lib/types/cart";
import { filterLocalItemsForServerSync } from "./sync-local-to-server";

const localItem = {
  id: "line-1",
  catalogProductId: "prod-1",
  configurationId: "variant-a",
} as CartLineItem;

describe("filterLocalItemsForServerSync", () => {
  it("syncs all local items when server cart is empty", () => {
    assert.equal(filterLocalItemsForServerSync([localItem], []).length, 1);
  });

  it("skips local lines already present on the server cart", () => {
    const filtered = filterLocalItemsForServerSync([localItem], [
      {
        id: "server-line",
        product_id: "prod-1",
        product_variant_id: "variant-a",
        quantity: 1,
        unit_price: 100,
      },
    ]);

    assert.equal(filtered.length, 0);
  });
});
