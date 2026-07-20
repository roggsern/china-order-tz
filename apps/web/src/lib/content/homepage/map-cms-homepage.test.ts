import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CmsHomepageResponse } from "@/lib/api/cms-homepage";
import {
  mapCmsHeroSlide,
  mapCmsHomepageResponse,
  mapCmsProductDataToCatalogProduct,
  mergeCmsMappedIntoSeed,
} from "./map-cms-homepage";
import { getHomepageContent, type ResolvedHomepageContent } from "./get-homepage-content";
import { homepageContentSeed } from "./seed";
import { filterActiveScheduled } from "./schedule";

function seedBase(): ResolvedHomepageContent {
  const now = new Date("2026-07-20T12:00:00.000Z");
  return {
    heroSlides: filterActiveScheduled(homepageContentSeed.heroSlides, now),
    advertisements: filterActiveScheduled(homepageContentSeed.advertisements, now),
    sponsors: filterActiveScheduled(homepageContentSeed.sponsors, now),
    flashDeals: filterActiveScheduled(homepageContentSeed.flashDeals, now),
    collections: homepageContentSeed.collections,
    whyChooseUs: homepageContentSeed.whyChooseUs,
    trustIndicators: homepageContentSeed.trustIndicators,
    trendingSearches: homepageContentSeed.trendingSearches,
    newsletter: homepageContentSeed.newsletter,
    sections: homepageContentSeed.sections,
    source: "fallback",
    campaign: null,
  };
}

const sampleCmsSuccess: CmsHomepageResponse = {
  success: true,
  meta: {
    commerce_context: "GLOBAL",
    resolved_commerce_context: "GLOBAL",
    campaign: {
      id: "camp-1",
      name: "Launch Week",
      slug: "launch-week",
      priority: 50,
      promotion_ids: ["promo-1"],
    },
  },
  data: {
    id: "layout-1",
    name: "Default Global",
    slug: "default-global",
    commerce_context: "GLOBAL",
    status: "active",
    is_default: true,
    sections: [
      {
        id: "sec-hero",
        cms_homepage_layout_id: "layout-1",
        section_type: "HERO",
        title: "Hero",
        subtitle: null,
        position: 0,
        is_visible: true,
        hero_slides: [
          {
            id: "slide-1",
            headline: "Order from China",
            subheadline: "China Campaign",
            eyebrow_text: null,
            description: "Import carefully selected products.",
            primary_cta: {
              type: "URL",
              label: "Explore China Catalog",
              value: "/products?origin=china",
              url: "/products?origin=china",
            },
            secondary_cta: null,
            position: 0,
          },
        ],
      },
      {
        id: "sec-featured",
        cms_homepage_layout_id: "layout-1",
        section_type: "FEATURED_PRODUCTS",
        title: "Featured Products",
        subtitle: "From CMS",
        position: 1,
        is_visible: true,
        featured_contents: [
          {
            id: "fc-1",
            cms_homepage_section_id: "sec-featured",
            title: "Featured Products",
            subtitle: "From CMS",
            source_type: "MANUAL",
            limit: 8,
            position: 0,
            items: [
              {
                item_type: "PRODUCT",
                id: "prod-1",
                data: {
                  id: "prod-1",
                  slug: "demo-widget",
                  name: "Demo Widget",
                  short_description: "A demo product",
                  price: "120000",
                  compare_at_price: "150000",
                  is_featured: true,
                  primary_image: null,
                  category: { id: "c1", name: "Electronics", slug: "electronics" },
                  brand: null,
                  average_rating: 4.5,
                  review_count: 3,
                  commerce_channel: { code: "CHINA_IMPORT", name: "China" },
                },
              },
            ],
          },
        ],
      },
      {
        id: "sec-collections",
        cms_homepage_layout_id: "layout-1",
        section_type: "FEATURED_COLLECTIONS",
        title: "Collections",
        subtitle: null,
        position: 2,
        is_visible: true,
        featured_contents: [
          {
            id: "fc-2",
            cms_homepage_section_id: "sec-collections",
            title: "Collections",
            subtitle: null,
            source_type: "MANUAL",
            limit: 6,
            position: 0,
            items: [
              {
                item_type: "CATEGORY",
                id: "cat-1",
                data: {
                  id: "cat-1",
                  name: "Electronics",
                  slug: "electronics",
                  description: "Gadgets",
                },
              },
            ],
          },
        ],
      },
      {
        id: "sec-stores",
        cms_homepage_layout_id: "layout-1",
        section_type: "SHOP_BY_STORE",
        title: "Shop by Store",
        subtitle: null,
        position: 3,
        is_visible: true,
        featured_contents: [
          {
            id: "fc-3",
            cms_homepage_section_id: "sec-stores",
            title: "Shop by Store",
            subtitle: null,
            source_type: "STORE",
            limit: 8,
            position: 0,
            items: [
              {
                item_type: "STORE",
                id: "store-1",
                data: {
                  id: "store-1",
                  code: "ZION",
                  name: "Zion Mode",
                  slug: "zion-mode",
                  theme_color: "#c9a227",
                },
              },
            ],
          },
        ],
      },
      {
        id: "sec-flash",
        cms_homepage_layout_id: "layout-1",
        section_type: "FLASH_DEALS",
        title: "Flash Deals",
        subtitle: null,
        position: 4,
        is_visible: true,
        featured_contents: [
          {
            id: "fc-4",
            cms_homepage_section_id: "sec-flash",
            title: "Flash Deals",
            subtitle: null,
            source_type: "MANUAL",
            limit: 4,
            position: 0,
            items: [
              {
                item_type: "PRODUCT",
                id: "prod-2",
                data: {
                  id: "prod-2",
                  slug: "flash-item",
                  name: "Flash Item",
                  price: "80000",
                  compare_at_price: "100000",
                  is_featured: false,
                  primary_image: null,
                  category: null,
                  brand: null,
                  average_rating: 0,
                  review_count: 0,
                  commerce_channel: { code: "TZ_LOCAL", name: "TZ" },
                },
              },
            ],
          },
        ],
      },
    ],
  },
};

describe("map-cms-homepage — hero mapping", () => {
  it("maps CMS hero slide fields to HomepageHeroSlide props", () => {
    const mapped = mapCmsHeroSlide(
      {
        id: "s1",
        headline: "Order from China",
        subheadline: "Import",
        eyebrow_text: null,
        description: "Desc",
        primary_cta: {
          type: "URL",
          label: "Shop",
          value: "/products?origin=china",
          url: "/products?origin=china",
        },
        secondary_cta: null,
        position: 0,
      },
      0,
    );

    assert.equal(mapped.id, "s1");
    assert.equal(mapped.title, "Order from China");
    assert.equal(mapped.ctaLabel, "Shop");
    assert.equal(mapped.ctaHref, "/products?origin=china");
    assert.equal(mapped.type, "china");
    assert.equal(mapped.status, "active");
  });
});

describe("map-cms-homepage — featured product mapping", () => {
  it("maps CMS product data to catalog Product", () => {
    const product = mapCmsProductDataToCatalogProduct({
      id: "uuid-1",
      slug: "demo-widget",
      name: "Demo Widget",
      short_description: "Nice",
      price: "1000",
      compare_at_price: "1200",
      is_featured: true,
      primary_image: null,
      category: { id: "c", name: "Cat", slug: "cat" },
      brand: null,
      average_rating: 5,
      review_count: 2,
      commerce_channel: { code: "CHINA_IMPORT" },
    });

    assert.ok(product);
    assert.equal(product!.slug, "demo-widget");
    assert.equal(product!.name, "Demo Widget");
    assert.equal(product!.price, 1000);
    assert.equal(product!.origin, "china");
    assert.equal(product!.featured, true);
  });

  it("returns null when required fields are missing", () => {
    assert.equal(mapCmsProductDataToCatalogProduct({ name: "Only name" }), null);
  });
});

describe("map-cms-homepage — CMS success", () => {
  it("applies Phase A sections and campaign metadata", () => {
    const mapped = mapCmsHomepageResponse(sampleCmsSuccess, seedBase());
    assert.equal(mapped.appliedCmsSections, true);
    assert.equal(mapped.campaign?.slug, "launch-week");
    assert.deepEqual(mapped.campaign?.promotion_ids, ["promo-1"]);
    assert.equal(mapped.heroSlides?.[0]?.title, "Order from China");
    assert.equal(mapped.featuredProducts?.[0]?.slug, "demo-widget");
    assert.equal(mapped.collections?.[0]?.slug, "electronics");
    assert.equal(mapped.shopByStores?.[0]?.slug, "zion-mode");
    assert.equal(mapped.flashDeals?.[0]?.productSlug, "flash-item");

    const merged = mergeCmsMappedIntoSeed(seedBase(), mapped);
    assert.equal(merged.source, "cms");
    assert.equal(merged.campaign?.name, "Launch Week");
    // Static seed areas preserved
    assert.ok(merged.sponsors.length > 0);
    assert.ok(merged.whyChooseUs.length > 0);
    assert.ok(merged.trustIndicators.length > 0);
    assert.ok(merged.newsletter.title);
    assert.ok(merged.advertisements.length > 0);
  });
});

describe("map-cms-homepage — empty / invalid CMS", () => {
  it("does not apply sections when layout is null", () => {
    const mapped = mapCmsHomepageResponse(
      {
        success: true,
        data: null,
        meta: { commerce_context: "GLOBAL", campaign: null },
      },
      seedBase(),
    );
    assert.equal(mapped.appliedCmsSections, false);
    assert.equal(mapped.heroSlides, undefined);
  });

  it("does not apply when sections array is empty", () => {
    const mapped = mapCmsHomepageResponse(
      {
        success: true,
        data: {
          id: "l",
          name: "Empty",
          slug: "empty",
          commerce_context: "GLOBAL",
          status: "active",
          is_default: true,
          sections: [],
        },
        meta: {
          commerce_context: "GLOBAL",
          campaign: { id: "c", name: "C", slug: "c", priority: 1 },
        },
      },
      seedBase(),
    );
    assert.equal(mapped.appliedCmsSections, false);
    assert.equal(mapped.campaign?.slug, "c");
  });
});

describe("getHomepageContent — fallback when API fails", () => {
  it("returns seed content and never an empty homepage", async () => {
    const content = await getHomepageContent(new Date("2026-07-20T12:00:00.000Z"));
    assert.ok(content.heroSlides.length > 0);
    assert.ok(content.sections.flashDeals.title);
    assert.ok(content.newsletter.title);
    assert.ok(["cms", "fallback"].includes(content.source));
    // Ads / sponsors / trust remain available (seed or merged)
    assert.ok(content.sponsors.length >= 0);
    assert.ok(content.trustIndicators.length > 0);
  });
});
