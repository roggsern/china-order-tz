import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { ApiCatalogCategory } from "@/lib/api/products";
import {
  mapChinaCategoriesToHomepageCollections,
  mapChinaCategoryToHomepageCollection,
  resolveHomepageFeaturedCollections,
} from "./homepage-collections";
import type { HomepageCollection } from "./types";

const electronicsCategory: ApiCatalogCategory = {
  id: "cat-electronics",
  name: "Electronics",
  slug: "electronics",
};

const cmsCollections: HomepageCollection[] = [
  {
    id: "cms-1",
    name: "CMS Electronics",
    slug: "electronics",
    description: "Configured in CMS",
    href: "/products?origin=china&category=electronics",
    icon: "grid",
    gradient: "from-sky-500 to-blue-600",
  },
];

describe("homepage-collections — catalog mapping", () => {
  it("maps China catalog categories to origin=china taxonomy links", () => {
    const collection = mapChinaCategoryToHomepageCollection(electronicsCategory);

    assert.equal(collection.href, "/products?origin=china&category=electronics");
    assert.equal(collection.slug, "electronics");
    assert.equal(collection.name, "Electronics");
    assert.match(collection.href, /^\/products\?origin=china&category=/);
    assert.doesNotMatch(collection.href, /buy-from-tz/);
    assert.doesNotMatch(collection.href, /search=/);
  });

  it("maps multiple live China categories", () => {
    const collections = mapChinaCategoriesToHomepageCollections([
      electronicsCategory,
      { id: "cat-beauty", name: "Beauty", slug: "beauty" },
    ]);

    assert.equal(collections.length, 2);
    assert.equal(collections[0]?.href, "/products?origin=china&category=electronics");
    assert.equal(collections[1]?.href, "/products?origin=china&category=beauty");
  });
});

describe("homepage-collections — resolution priority", () => {
  it("prefers CMS configured collections over catalog fallback", () => {
    const catalogFallback: HomepageCollection[] = [
      {
        id: "live-1",
        name: "Live Beauty",
        slug: "beauty",
        description: "From catalog",
        href: "/products?origin=china&category=beauty",
        icon: "💄",
        gradient: "from-rose-400/20 to-zinc-100",
      },
    ];

    const resolved = resolveHomepageFeaturedCollections(cmsCollections, catalogFallback);

    assert.deepEqual(resolved, cmsCollections);
    assert.equal(resolved[0]?.name, "CMS Electronics");
  });

  it("falls back to live China catalog when CMS collections are empty", () => {
    const catalogFallback = mapChinaCategoriesToHomepageCollections([electronicsCategory]);
    const resolved = resolveHomepageFeaturedCollections([], catalogFallback);

    assert.equal(resolved.length, 1);
    assert.equal(resolved[0]?.slug, "electronics");
    assert.equal(resolved[0]?.href, "/products?origin=china&category=electronics");
  });

  it("returns empty collections when CMS and catalog are both empty", () => {
    const resolved = resolveHomepageFeaturedCollections(undefined, []);
    assert.deepEqual(resolved, []);
  });

  it("never surfaces Buy from TZ routes in catalog fallback links", () => {
    const collections = mapChinaCategoriesToHomepageCollections([
      electronicsCategory,
      { id: "cat-fashion", name: "Fashion", slug: "fashion" },
    ]);

    for (const collection of collections) {
      assert.doesNotMatch(collection.href, /buy-from-tz/);
      assert.doesNotMatch(collection.href, /search=/);
      assert.match(collection.href, /origin=china/);
    }
  });
});
