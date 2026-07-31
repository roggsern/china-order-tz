import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { PRODUCT_PLACEHOLDER_IMAGE } from "@/lib/catalog/product-images";
import { resolveOrderItemImageUrl } from "./resolve-order-item-image";

describe("resolveOrderItemImageUrl", () => {
  it("keeps absolute image URLs", () => {
    assert.equal(
      resolveOrderItemImageUrl({
        snapshotUrl: "https://cdn.example.com/product.jpg",
      }),
      "https://cdn.example.com/product.jpg",
    );
  });

  it("normalizes relative Laravel storage paths", () => {
    const previousApiUrl = process.env.NEXT_PUBLIC_API_URL;
    process.env.NEXT_PUBLIC_API_URL = "http://localhost:8000";

    try {
      const resolved = resolveOrderItemImageUrl({
        snapshotUrl: "demo-products/phone.jpg",
      });

      assert.match(resolved, /\/storage\/demo-products\/phone\.jpg$/);
      assert.notEqual(resolved, PRODUCT_PLACEHOLDER_IMAGE);
    } finally {
      if (previousApiUrl === undefined) {
        delete process.env.NEXT_PUBLIC_API_URL;
      } else {
        process.env.NEXT_PUBLIC_API_URL = previousApiUrl;
      }
    }
  });

  it("prefers historical snapshot over live product media", () => {
    assert.equal(
      resolveOrderItemImageUrl({
        snapshotUrl: "https://cdn.example.com/order-snapshot.jpg",
        productPrimaryImageUrl: "https://cdn.example.com/live-primary.jpg",
      }),
      "https://cdn.example.com/order-snapshot.jpg",
    );
  });

  it("falls back to current product primary image when snapshot is missing", () => {
    const resolved = resolveOrderItemImageUrl({
      snapshotUrl: null,
      productPrimaryImageUrl: "https://cdn.example.com/current-primary.jpg",
    });

    assert.equal(resolved, "https://cdn.example.com/current-primary.jpg");
  });

  it("uses placeholder when all media is missing", () => {
    assert.equal(
      resolveOrderItemImageUrl({
        snapshotUrl: null,
        productPrimaryImageUrl: null,
      }),
      PRODUCT_PLACEHOLDER_IMAGE,
    );
  });
});
