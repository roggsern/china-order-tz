import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveStorefrontBrandQuery } from "./storefront-brand-filter";

describe("resolveStorefrontBrandQuery", () => {
  it("uses China storefront brands with category slug when origin=china", () => {
    assert.deepEqual(
      resolveStorefrontBrandQuery({
        origin: "china",
        categorySlug: "womens-fashion",
      }),
      { source: "china", categorySlug: "womens-fashion" },
    );
  });

  it("uses China storefront brands without category when origin=china and category empty", () => {
    assert.deepEqual(
      resolveStorefrontBrandQuery({
        origin: "china",
        categorySlug: "  ",
      }),
      { source: "china", categorySlug: undefined },
    );
  });

  it("keeps catalog brands for non-China contexts", () => {
    assert.deepEqual(
      resolveStorefrontBrandQuery({
        origin: "tz",
        categorySlug: "womens-fashion",
      }),
      { source: "catalog" },
    );

    assert.deepEqual(
      resolveStorefrontBrandQuery({
        origin: null,
        categorySlug: "electronics",
      }),
      { source: "catalog" },
    );
  });

  it("scopes electronics under China to the electronics category slug", () => {
    assert.deepEqual(
      resolveStorefrontBrandQuery({
        origin: "china",
        categorySlug: "electronics",
      }),
      { source: "china", categorySlug: "electronics" },
    );
  });
});
