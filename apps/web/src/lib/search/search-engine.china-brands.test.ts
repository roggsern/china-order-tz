import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { Category, Product } from "@/lib/types/catalog";
import { clearSearchQueryCache, searchCatalog } from "./search-engine";

function product(overrides: Partial<Product> & Pick<Product, "id" | "slug" | "name">): Product {
  return {
    description: overrides.name,
    price: 10_000,
    oldPrice: 0,
    rating: 4,
    reviews: 10,
    badge: "",
    gradient: "from-zinc-100 to-zinc-200",
    emoji: "📦",
    categorySlug: "electronics",
    stock: 5,
    images: [],
    features: [],
    specifications: [],
    customerReviews: [],
    featured: false,
    status: "active",
    origin: "china",
    isPurchasable: true,
    availabilityStatus: "available",
    catalogProductId: `catalog-${overrides.id}`,
    badges: [],
    trustBadges: [],
    ...overrides,
  };
}

describe("searchCatalog China brand suggestions", () => {
  it("includes China-scoped brand suggestions that match the query", () => {
    clearSearchQueryCache();

    const products: Product[] = [
      product({
        id: 1,
        slug: "china-phone",
        name: "SearchBrandCo Phone",
        brand: "SearchBrandCo",
        origin: "china",
      }),
    ];

    const liveBrands: Category[] = [
      {
        slug: "search-brand-co",
        name: "SearchBrandCo",
        description: "SearchBrandCo brand",
        gradient: "from-amber-200 via-orange-100 to-rose-200",
        icon: "🏷",
        searchSuggestionType: "brand",
      },
      {
        slug: "other-brand",
        name: "OtherBrand",
        description: "OtherBrand brand",
        gradient: "from-amber-200 via-orange-100 to-rose-200",
        icon: "🏷",
        searchSuggestionType: "brand",
      },
    ];

    const results = searchCatalog(products, "SearchBrand", {
      origin: "china",
      liveBrands,
      liveCategories: [],
    });

    assert.ok(
      results.categories.some(
        (entry) =>
          entry.slug === "search-brand-co" && entry.searchSuggestionType === "brand",
      ),
      "expected China brand suggestion to appear",
    );
    assert.ok(
      !results.categories.some((entry) => entry.slug === "other-brand"),
      "unrelated brands must not appear",
    );
  });
});
