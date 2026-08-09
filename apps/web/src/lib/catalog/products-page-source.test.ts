import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveProductsPageSource } from "./products";

describe("resolveProductsPageSource", () => {
  it("uses China storefront when origin=china", () => {
    assert.equal(resolveProductsPageSource("china"), "china-storefront");
  });

  it("keeps generic catalog for All and TZ", () => {
    assert.equal(resolveProductsPageSource(undefined), "catalog");
    assert.equal(resolveProductsPageSource("tz"), "catalog");
  });
});
