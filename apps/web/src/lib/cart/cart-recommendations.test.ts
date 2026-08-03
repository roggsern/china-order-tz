import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";
import type { Product } from "@/lib/types/catalog";
import {
  buildCartRecommendedProducts,
  buildFrequentlyBoughtTogetherProducts,
  filterCartRecommendationCatalog,
  isCartRecommendationProduct,
} from "./cart-recommendations";

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

describe("cart-recommendations", () => {
  it("filters inactive and unpurchasable products from recommendations", () => {
    const catalog = [
      product({ id: 1, slug: "live-wig", name: "Live Wig" }),
      product({
        id: 2,
        slug: "iphone-17-pro-max",
        name: "iPhone 17 Pro Max",
        status: "archived",
      }),
      product({
        id: 3,
        slug: "human-hair",
        name: "Human Hair",
        isPurchasable: false,
        availabilityStatus: "unavailable",
      }),
    ];

    const eligible = filterCartRecommendationCatalog(catalog);

    assert.equal(eligible.length, 1);
    assert.equal(eligible[0]?.slug, "live-wig");
    assert.equal(isCartRecommendationProduct(catalog[1]!), false);
  });

  it("builds frequently bought together from live catalog and excludes cart items", () => {
    const catalog = [
      product({
        id: 10,
        slug: "in-cart-item",
        name: "In Cart Item",
        categorySlug: "beauty-cosmetics",
        featured: true,
        rating: 5,
      }),
      product({
        id: 11,
        slug: "matching-category",
        name: "Matching Category",
        categorySlug: "beauty-cosmetics",
        featured: false,
        rating: 4.5,
      }),
      product({
        id: 12,
        slug: "other-category",
        name: "Other Category",
        categorySlug: "electronics",
        featured: true,
        rating: 5,
      }),
      product({
        id: 13,
        slug: "legacy-blouse",
        name: "BLOUSE",
        status: "archived",
        categorySlug: "beauty-cosmetics",
      }),
    ];

    const recommendations = buildFrequentlyBoughtTogetherProducts(
      catalog,
      [{ productId: 10, slug: "in-cart-item", catalogProductId: "catalog-10" }],
      ["beauty-cosmetics"],
      4,
    );

    assert.deepEqual(
      recommendations.map((entry) => entry.slug),
      ["matching-category"],
    );
    assert.ok(!recommendations.some((entry) => entry.slug === "in-cart-item"));
    assert.ok(!recommendations.some((entry) => entry.slug === "legacy-blouse"));
  });

  it("falls back to featured and rating sort when no category matches", () => {
    const catalog = [
      product({ id: 21, slug: "featured-a", name: "Featured A", featured: true, rating: 4.2 }),
      product({ id: 22, slug: "featured-b", name: "Featured B", featured: true, rating: 4.8 }),
      product({ id: 23, slug: "regular", name: "Regular", featured: false, rating: 5 }),
    ];

    const recommendations = buildCartRecommendedProducts(catalog, 2);

    assert.deepEqual(
      recommendations.map((entry) => entry.slug),
      ["featured-b", "featured-a"],
    );
  });
});

describe("cart recommendation components integration", () => {
  it("loads live catalog via fetchClientCatalogProducts", () => {
    for (const relativePath of [
      "src/components/cart/CartFrequentlyBoughtTogether.tsx",
      "src/components/cart/CartRecommendedProducts.tsx",
    ]) {
      const source = readFileSync(join(process.cwd(), relativePath), "utf8");

      assert.match(source, /fetchClientCatalogProducts/);
      assert.doesNotMatch(source, /productService\.list\(/);
    }
  });
});
