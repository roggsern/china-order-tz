import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { UnifiedSearchSuggestData } from "@/lib/api/marketplace-search";
import {
  mapUnifiedSuggestToSearchResults,
  resolveProductSuggestionHref,
  resolveSuggestionHref,
} from "./map-unified-suggest";

function sampleSuggestPayload(): UnifiedSearchSuggestData {
  return {
    q: "zion",
    scope: "all",
    products: [
      {
        id: "p-china",
        slug: "china-zion-gown",
        name: "Imported Evening Gown",
        short_description: null,
        price: 45000,
        compare_at_price: null,
        is_featured: false,
        product_condition: null,
        product_condition_label: null,
        primary_image: null,
        average_rating: 0,
        review_count: 0,
        shipping_prices: { air: null, sea: null },
        requires_china_shipping: true,
        commerce_channel_code: "CHINA_IMPORT",
        marketplace: "china",
        brand: { id: "b1", slug: "zion-mode-brand", name: "Zion Mode" },
        store: null,
        matched_on: ["brand"],
        relevance_score: 450,
        category: null,
        variants: [],
      },
      {
        id: "p-tz",
        slug: "tz-zion-dress",
        name: "Local Boutique Dress",
        short_description: null,
        price: 25000,
        compare_at_price: null,
        is_featured: false,
        product_condition: null,
        product_condition_label: null,
        primary_image: null,
        average_rating: 0,
        review_count: 0,
        shipping_prices: { air: null, sea: null },
        requires_china_shipping: false,
        commerce_channel_code: "TZ_LOCAL",
        marketplace: "tz",
        brand: null,
        store: { id: "s1", slug: "zion-mode", name: "ZION MODE" },
        matched_on: ["store"],
        relevance_score: 400,
        category: null,
        variants: [],
      },
    ],
    brands: [
      {
        kind: "catalog_brand",
        id: "b1",
        slug: "zion-mode-brand",
        name: "Zion Mode",
        relevance_score: 450,
      },
    ],
    stores: [
      {
        kind: "tz_store",
        id: "s1",
        slug: "zion-mode",
        name: "ZION MODE",
        relevance_score: 400,
      },
    ],
    categories: [
      {
        kind: "category",
        id: "c1",
        slug: "zion-dresses",
        name: "Zion Dresses",
        relevance_score: 300,
      },
    ],
  };
}

describe("mapUnifiedSuggestToSearchResults", () => {
  it("maps brand and store suggestions into separate buckets", () => {
    const results = mapUnifiedSuggestToSearchResults(sampleSuggestPayload());

    assert.equal(results.brands.length, 1);
    assert.equal(results.brands[0]?.searchSuggestionType, "brand");
    assert.equal(results.brands[0]?.slug, "zion-mode-brand");

    assert.equal(results.stores.length, 1);
    assert.equal(results.stores[0]?.searchSuggestionType, "store");
    assert.equal(results.stores[0]?.slug, "zion-mode");

    assert.equal(results.categories.length, 1);
    assert.equal(results.categories[0]?.slug, "zion-dresses");
  });

  it("keeps product click navigation on /products/{slug}", () => {
    const results = mapUnifiedSuggestToSearchResults(sampleSuggestPayload());
    const china = results.products.find((p) => p.slug === "china-zion-gown");
    const tz = results.products.find((p) => p.slug === "tz-zion-dress");

    assert.ok(china);
    assert.ok(tz);
    assert.equal(resolveProductSuggestionHref(china!), "/products/china-zion-gown");
    assert.equal(resolveProductSuggestionHref(tz!), "/products/tz-zion-dress");
  });

  it("builds brand and store suggestion hrefs correctly", () => {
    const results = mapUnifiedSuggestToSearchResults(sampleSuggestPayload());

    assert.equal(
      resolveSuggestionHref(results.brands[0]!, "china"),
      "/products?origin=china&brand=zion-mode-brand",
    );
    assert.equal(resolveSuggestionHref(results.stores[0]!), "/buy-from-tz/zion-mode");
    assert.equal(
      resolveSuggestionHref(results.categories[0]!),
      "/categories/zion-dresses",
    );
  });

  it("routes term suggestions to unified /search with scope", () => {
    const results = mapUnifiedSuggestToSearchResults(sampleSuggestPayload());
    assert.ok(results.terms.length > 0);
    assert.match(results.terms[0]!.href, /^\/search\?/);
    assert.match(results.terms[0]!.href, /scope=all/);
  });
});
