import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it } from "node:test";
import path from "node:path";

const pagePath = path.resolve(process.cwd(), "src/app/(storefront)/page.tsx");
const homeCatalogPath = path.resolve(process.cwd(), "src/lib/catalog/home-catalog.ts");
const productsApiPath = path.resolve(process.cwd(), "src/lib/api/products.ts");
const chinaApiPath = path.resolve(process.cwd(), "src/lib/api/china-storefront.ts");
const tzApiPath = path.resolve(process.cwd(), "src/lib/api/tz-stores.ts");

describe("homepage Wave 3 SSR rail contracts", () => {
  it("loads China and TZ new arrivals in parallel", () => {
    const source = readFileSync(pagePath, "utf8");
    assert.match(source, /Promise\.all\(\[/);
    assert.match(source, /getHomeNewArrivalsByOrigin\("china"/);
    assert.match(source, /getHomeNewArrivalsByOrigin\("tz"/);
  });

  it("avoids oversized homepage product overfetch", () => {
    const source = readFileSync(homeCatalogPath, "utf8");
    assert.match(
      source,
      /getHomeNewArrivalsByOrigin = cache\(\s*async \(origin: ProductOrigin, limit = 4\): Promise<Product\[]> => \{\s*const result = await getProductsPage\(\{\s*origin,\s*per_page: limit,/s,
    );
    assert.doesNotMatch(source, /Math\.max\(limit \* 3,\s*24\)/);
    assert.match(source, /Math\.max\(limit \* 2,\s*12\)/);
  });

  it("uses short public revalidate for non-search catalog fetches", () => {
    for (const file of [productsApiPath, chinaApiPath, tzApiPath]) {
      const source = readFileSync(file, "utf8");
      assert.match(source, /next:\s*\{\s*revalidate:\s*120\s*\}/);
      assert.match(source, /canRevalidate/);
    }
  });

  it("keeps Suspense rails for shop/featured/new/best sections", () => {
    const source = readFileSync(pagePath, "utf8");
    assert.match(source, /CommercialShopByStore/);
    assert.match(source, /CommercialFeaturedProducts/);
    assert.match(source, /CommercialNewArrivals/);
    assert.match(source, /CommercialBestSellers/);
    assert.match(source, /FeaturedCollections/);
    assert.match(source, /HeroCarousel/);
  });
});
