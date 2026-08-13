import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildCatalogProductConfigurationBffPath,
  buildCatalogProductCheckoutSummaryBffPath,
  buildCatalogProductQuoteBffPath,
  buildCatalogProductShowBffPath,
  buildStorefrontProductDetailPath,
  catalogProductCheckoutSummaryUpstreamUrl,
  catalogProductConfigurationUpstreamUrl,
  catalogProductShowUpstreamUrl,
  parseCatalogProductConfigurationSlug,
  parseCatalogProductQuoteSlug,
  parseCatalogProductSlug,
  parseStorefrontProductSlug,
  resolveCatalogProductSlugFromRequest,
} from "@/lib/api/catalog-proxy";

describe("catalog product slug proxy helpers", () => {
  it("builds static BFF paths for product show, configuration, and quote", () => {
    assert.equal(
      buildCatalogProductShowBffPath("tie-front-blouse"),
      "/api/catalog/products?slug=tie-front-blouse",
    );
    assert.equal(
      buildCatalogProductCheckoutSummaryBffPath("tie-front-blouse"),
      "/api/catalog/products/checkout-summary?slug=tie-front-blouse",
    );
    assert.equal(
      buildCatalogProductConfigurationBffPath("tie-front-blouse"),
      "/api/catalog/products/configuration?slug=tie-front-blouse",
    );
    assert.equal(
      buildCatalogProductQuoteBffPath("tie-front-blouse"),
      "/api/catalog/products/quote?slug=tie-front-blouse",
    );
    assert.equal(
      buildStorefrontProductDetailPath("tie-front-blouse"),
      "/products/detail?slug=tie-front-blouse",
    );
  });

  it("builds upstream Laravel URLs for product show and configuration", () => {
    assert.equal(
      catalogProductShowUpstreamUrl("http://nginx", "tie-front-blouse"),
      "http://nginx/api/v1/products/tie-front-blouse",
    );
    assert.equal(
      catalogProductCheckoutSummaryUpstreamUrl("http://nginx", "tie-front-blouse"),
      "http://nginx/api/v1/products/tie-front-blouse/checkout-summary",
    );
    assert.equal(
      catalogProductConfigurationUpstreamUrl("http://nginx", "tie-front-blouse", "foo=bar"),
      "http://nginx/api/v1/products/tie-front-blouse/configuration?foo=bar",
    );
  });

  it("parses storefront product detail slugs", () => {
    assert.equal(parseStorefrontProductSlug("/products/tie-front-blouse"), "tie-front-blouse");
    assert.equal(parseStorefrontProductSlug("/products/detail"), null);
    assert.equal(parseStorefrontProductSlug("/products"), null);
  });

  it("parses catalog BFF slug paths", () => {
    assert.equal(
      parseCatalogProductSlug("/api/catalog/products/tie-front-blouse"),
      "tie-front-blouse",
    );
    assert.equal(parseCatalogProductSlug("/api/catalog/products/configuration"), null);
    assert.equal(
      parseCatalogProductConfigurationSlug("/api/catalog/products/tie-front-blouse/configuration"),
      "tie-front-blouse",
    );
    assert.equal(
      parseCatalogProductQuoteSlug("/api/catalog/products/tie-front-blouse/quote"),
      "tie-front-blouse",
    );
  });

  it("resolves product slug from query, pathname, or rewrite header", () => {
    const fromQuery = new Request("http://localhost/api/catalog/products?slug=tie-front-blouse");
    assert.equal(resolveCatalogProductSlugFromRequest(fromQuery), "tie-front-blouse");

    const fromPath = new Request("http://localhost/api/catalog/products/tie-front-blouse");
    assert.equal(resolveCatalogProductSlugFromRequest(fromPath), "tie-front-blouse");

    const fromHeader = new Request("http://localhost/api/catalog/products", {
      headers: { "x-catalog-product-slug": "tie-front-blouse" },
    });
    assert.equal(resolveCatalogProductSlugFromRequest(fromHeader), "tie-front-blouse");
  });
});
