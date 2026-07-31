import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { mapServerWishlistItems } from "./customer-wishlist";

describe("customer wishlist mapping", () => {
  it("maps server wishlist items with catalog ids and metadata", () => {
    const mapped = mapServerWishlistItems(
      [
        {
          id: "wish-1",
          product_id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
          created_at: "2026-01-15T10:00:00.000Z",
          product: {
            id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
            slug: "mapped-product",
            name: "Mapped Product",
          },
        },
      ],
      new Map([
        [
          "019f7a6e-4d46-7376-aca4-aed79f33519b",
          {
            productId: 999,
            emoji: "✨",
            price: 12000,
          },
        ],
      ]),
    );

    assert.equal(mapped.length, 1);
    assert.equal(mapped[0]?.catalogProductId, "019f7a6e-4d46-7376-aca4-aed79f33519b");
    assert.equal(mapped[0]?.slug, "mapped-product");
    assert.equal(mapped[0]?.productId, 999);
    assert.equal(mapped[0]?.emoji, "✨");
    assert.equal(mapped[0]?.price, 12000);
  });
});
