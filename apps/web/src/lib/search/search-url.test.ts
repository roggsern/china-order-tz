import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildSearchBrandHref,
  buildSearchCategoryHref,
  buildProductSearchHref,
  resolveDefaultSearchMarketplaceScope,
} from "./search-url";

describe("resolveDefaultSearchMarketplaceScope", () => {
  it("defaults to China when URL origin=china", () => {
    assert.equal(
      resolveDefaultSearchMarketplaceScope({ origin: "china" }),
      "china",
    );
  });

  it("defaults to Buy from Dar when URL origin=tz", () => {
    assert.equal(resolveDefaultSearchMarketplaceScope({ origin: "tz" }), "tz");
  });

  it("defaults to All when products URL has no origin", () => {
    assert.equal(
      resolveDefaultSearchMarketplaceScope({
        origin: null,
        pathname: "/products",
      }),
      "all",
    );
  });

  it("defaults to Buy from Dar on buy-from-tz routes without origin param", () => {
    assert.equal(
      resolveDefaultSearchMarketplaceScope({
        origin: null,
        pathname: "/buy-from-tz/zion-mode",
      }),
      "tz",
    );
  });
});

describe("buildSearchCategoryHref", () => {
  it("keeps China listing context for China category suggestions", () => {
    assert.equal(
      buildSearchCategoryHref("power-backup", "china"),
      "/products?origin=china&category=power-backup",
    );
  });

  it("uses generic category route when scope is All", () => {
    assert.equal(buildSearchCategoryHref("electronics"), "/categories/electronics");
  });
});

describe("buildSearchBrandHref", () => {
  it("keeps China listing context for China brand suggestions", () => {
    assert.equal(
      buildSearchBrandHref("apple", "china"),
      "/products?origin=china&brand=apple",
    );
  });
});

describe("buildProductSearchHref", () => {
  it("keeps China context on product search result pages", () => {
    assert.equal(
      buildProductSearchHref("UPS", "china"),
      "/products?q=UPS&origin=china",
    );
  });
});
