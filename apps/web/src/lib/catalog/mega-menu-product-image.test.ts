import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { ChinaMegaMenuFeaturedProduct } from "@/lib/api/china-storefront";
import type { ApiCatalogProductCard } from "@/lib/api/products";
import { resolveMegaMenuProductImage } from "./mega-menu-product-image";

describe("resolveMegaMenuProductImage — Wave 2 slim contract", () => {
  it("resolves url from a slim mega-menu product without listing-card fields", () => {
    const slim: ChinaMegaMenuFeaturedProduct = {
      id: "p1",
      slug: "slim-phone",
      name: "Slim Phone",
      primary_image: {
        url: "https://cdn.example/phone.webp",
        path: null,
      },
      brand: { id: "b1", name: "BrandCo", slug: "brand-co" },
    };

    assert.equal(resolveMegaMenuProductImage(slim), "https://cdn.example/phone.webp");
  });

  it("falls back to path when url is absent", () => {
    const slim: ChinaMegaMenuFeaturedProduct = {
      id: "p2",
      slug: "path-only",
      name: "Path Only",
      primary_image: {
        path: "/media/path-only.jpg",
      },
    };

    assert.equal(resolveMegaMenuProductImage(slim), "/media/path-only.jpg");
  });

  it("returns null when primary_image is missing", () => {
    const slim: ChinaMegaMenuFeaturedProduct = {
      id: "p3",
      slug: "no-image",
      name: "No Image",
    };

    assert.equal(resolveMegaMenuProductImage(slim), null);
  });

  it("still accepts a full ApiCatalogProductCard structurally", () => {
    const full = {
      id: "p4",
      slug: "full-card",
      name: "Full Card",
      short_description: "Required on listing cards",
      price: 1000,
      compare_at_price: null,
      is_featured: true,
      primary_image: {
        id: "img-1",
        path: "/media/full.jpg",
        url: "https://cdn.example/full.jpg",
        alt_text: null,
      },
      category: null,
      brand: null,
      average_rating: 0,
      review_count: 0,
    } satisfies ApiCatalogProductCard;

    assert.equal(resolveMegaMenuProductImage(full), "https://cdn.example/full.jpg");
  });
});
