import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  resolveLiveSearchBrandSource,
  resolveLiveSearchProductSource,
} from "./catalog-source";
import {
  buildSearchBrandHref,
  buildSearchCategoryHref,
  buildSearchStoreHref,
  buildProductSearchHref,
  resolveDefaultSearchMarketplaceScope,
} from "./search-url";

describe("resolveDefaultSearchMarketplaceScope", () => {
  it("defaults to all when opening search from /products?origin=china", () => {
    assert.equal(
      resolveDefaultSearchMarketplaceScope({
        origin: "china",
        pathname: "/products",
      }),
      "all",
    );
  });

  it("defaults to all when opening search from /buy-from-tz", () => {
    assert.equal(
      resolveDefaultSearchMarketplaceScope({
        origin: null,
        pathname: "/buy-from-tz/zion-mode",
      }),
      "all",
    );
  });

  it("defaults to all on homepage and products without origin", () => {
    assert.equal(
      resolveDefaultSearchMarketplaceScope({
        origin: null,
        pathname: "/",
      }),
      "all",
    );
    assert.equal(
      resolveDefaultSearchMarketplaceScope({
        origin: null,
        pathname: "/products",
      }),
      "all",
    );
  });

  it("ignores URL origin=tz for the header search default", () => {
    assert.equal(resolveDefaultSearchMarketplaceScope({ origin: "tz" }), "all");
  });
});

describe("explicit marketplace tab source routing", () => {
  it("manual China selection still requests China storefront source", () => {
    assert.equal(resolveLiveSearchProductSource("china"), "china-storefront");
    assert.equal(resolveLiveSearchBrandSource("china"), "china-storefront");
  });

  it("manual TZ selection still requests catalog source (TZ client filter)", () => {
    assert.equal(resolveLiveSearchProductSource("tz"), "catalog");
    assert.equal(resolveLiveSearchBrandSource("tz"), "catalog");
  });

  it("default All selection requests catalog source", () => {
    assert.equal(resolveLiveSearchProductSource(undefined), "catalog");
    assert.equal(resolveLiveSearchBrandSource(undefined), "catalog");
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

describe("buildSearchStoreHref", () => {
  it("routes store suggestions to Buy from TZ storefront", () => {
    assert.equal(buildSearchStoreHref("zion-mode"), "/buy-from-tz/zion-mode");
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
