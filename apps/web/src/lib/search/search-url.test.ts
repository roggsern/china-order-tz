import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  resolveLiveSearchBrandSource,
  resolveLiveSearchProductSource,
} from "./catalog-source";
import {
  buildSearchBrandHref,
  buildSearchCategorySuggestionHref,
  buildSearchStoreHref,
  buildUnifiedSearchHref,
  resolveDefaultSearchMarketplaceScope,
  resolveSearchPageHeading,
  resolveSearchPageScope,
  resolveSearchPageScopeLabel,
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

describe("buildSearchCategorySuggestionHref", () => {
  it("routes All-scope category suggestions to unified search", () => {
    assert.equal(
      buildSearchCategorySuggestionHref("Power Backup / UPS", "all"),
      "/search?q=Power+Backup+%2F+UPS&scope=all",
    );
  });

  it("preserves China marketplace scope", () => {
    assert.equal(
      buildSearchCategorySuggestionHref("Power Backup / UPS", "china"),
      "/search?q=Power+Backup+%2F+UPS&scope=china",
    );
  });

  it("preserves TZ marketplace scope", () => {
    assert.equal(
      buildSearchCategorySuggestionHref("Electronics", "tz"),
      "/search?q=Electronics&scope=tz",
    );
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

describe("buildUnifiedSearchHref", () => {
  it("Enter navigation goes to /search with query", () => {
    assert.equal(buildUnifiedSearchHref("UPS", "all"), "/search?q=UPS&scope=all");
  });

  it("preserves China marketplace scope", () => {
    assert.equal(
      buildUnifiedSearchHref("UPS", "china"),
      "/search?q=UPS&scope=china",
    );
  });

  it("preserves TZ marketplace scope", () => {
    assert.equal(
      buildUnifiedSearchHref("zion", "tz"),
      "/search?q=zion&scope=tz",
    );
  });
});

describe("search page query state", () => {
  it("renders heading from query", () => {
    assert.equal(resolveSearchPageHeading("zion"), 'Results for "zion"');
    assert.equal(resolveSearchPageHeading("  "), "Search");
  });

  it("normalizes scope from URL", () => {
    assert.equal(resolveSearchPageScope("china"), "china");
    assert.equal(resolveSearchPageScope("tz"), "tz");
    assert.equal(resolveSearchPageScope("bogus"), "all");
    assert.equal(resolveSearchPageScope(undefined), "all");
  });

  it("labels TZ marketplace scope as Buy from TZ", () => {
    assert.equal(resolveSearchPageScopeLabel("tz"), "Buy from TZ");
    assert.equal(resolveSearchPageScopeLabel("china"), "China");
    assert.equal(resolveSearchPageScopeLabel("all"), "All marketplaces");
  });
});
