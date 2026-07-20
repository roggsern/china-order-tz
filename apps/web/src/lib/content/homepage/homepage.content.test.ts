import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  discountPercent,
  filterActiveScheduled,
  getAdsByPlacement,
  getHomepageContent,
  homepageContentSeed,
  isActivelyScheduled,
} from "./index";
import { STOREFRONT_NAV_LABELS } from "@/lib/storefront/navigation-policy";

describe("homepage commercial content — hero", () => {
  it("includes China, TZ, sponsor, and seasonal slide types", async () => {
    const content = await getHomepageContent();
    const types = new Set(content.heroSlides.map((slide) => slide.type));
    assert.ok(types.has("china"));
    assert.ok(types.has("tz"));
    assert.ok(types.has("sponsor"));
    assert.ok(types.has("seasonal"));
  });

  it("uses exact dual-journey labels on journey slides", async () => {
    const content = await getHomepageContent();
    const china = content.heroSlides.find((slide) => slide.type === "china");
    const tz = content.heroSlides.find((slide) => slide.type === "tz");
    assert.equal(china?.title, STOREFRONT_NAV_LABELS.orderFromChina);
    assert.equal(tz?.title, STOREFRONT_NAV_LABELS.buyFromTz);
    assert.equal(china?.ctaLabel, "Explore China Catalog");
    assert.equal(tz?.ctaLabel, "Explore TZ Stores");
  });

  it("orders hero slides by priority descending", async () => {
    const content = await getHomepageContent();
    const priorities = content.heroSlides.map((slide) => slide.priority);
    const sorted = [...priorities].sort((a, b) => b - a);
    assert.deepEqual(priorities, sorted);
  });
});

describe("homepage commercial content — ads & sponsors", () => {
  it("filters inactive and expired advertisements", () => {
    const now = new Date("2026-07-20T00:00:00.000Z");
    const mixed = [
      {
        ...homepageContentSeed.advertisements[0],
        id: "active",
        status: "active" as const,
        displayStart: "2020-01-01T00:00:00.000Z",
        displayEnd: "2099-01-01T00:00:00.000Z",
        priority: 10,
      },
      {
        ...homepageContentSeed.advertisements[0],
        id: "draft",
        status: "draft" as const,
        priority: 99,
      },
      {
        ...homepageContentSeed.advertisements[0],
        id: "expired",
        status: "active" as const,
        displayStart: "2020-01-01T00:00:00.000Z",
        displayEnd: "2021-01-01T00:00:00.000Z",
        priority: 50,
      },
    ];

    const active = filterActiveScheduled(mixed, now);
    assert.equal(active.length, 1);
    assert.equal(active[0]?.id, "active");
    assert.equal(isActivelyScheduled(mixed[1]!, now), false);
  });

  it("groups ads by placement", async () => {
    const content = await getHomepageContent();
    const mid = getAdsByPlacement(content.advertisements, "mid_page");
    const footer = getAdsByPlacement(content.advertisements, "footer");
    const banner = getAdsByPlacement(content.advertisements, "homepage_banner");
    assert.ok(mid.length >= 1);
    assert.ok(footer.length >= 1);
    assert.ok(banner.length >= 1);
    assert.ok(mid.every((ad) => ad.placement === "mid_page"));
  });

  it("renders a trusted partner sponsor grid from content", async () => {
    const content = await getHomepageContent();
    const names = content.sponsors.map((sponsor) => sponsor.name);
    assert.ok(names.includes("NMB"));
    assert.ok(names.includes("Visa"));
    assert.ok(names.includes("Vodacom"));
    assert.ok(content.sponsors.length >= 6);
  });
});

describe("homepage commercial content — flash deals & sections", () => {
  it("computes discount percent for flash deals", () => {
    assert.equal(discountPercent(100, 75), 25);
    assert.equal(discountPercent(0, 10), 0);
    assert.equal(discountPercent(50, 60), 0);
  });

  it("includes flash deals with countdown end dates", async () => {
    const content = await getHomepageContent();
    assert.ok(content.flashDeals.length >= 2);
    for (const deal of content.flashDeals) {
      assert.ok(deal.newPrice < deal.oldPrice);
      assert.ok(Date.parse(deal.endsAt) > Date.now() - 1000);
      assert.ok(deal.href.length > 0);
    }
  });

  it("exposes store and collection section copy for CMS-ready UI", async () => {
    const content = await getHomepageContent();
    assert.equal(content.sections.shopByStore.title, "Shop by Store");
    assert.equal(content.sections.collections.title, "Featured Collections");
    assert.ok(content.collections.some((item) => item.name === "Electronics"));
    assert.ok(content.trendingSearches.includes("iPhone"));
    assert.equal(content.newsletter.title, "Stay Updated");
  });

  it("trust indicators describe the platform, not supplier verification", async () => {
    const content = await getHomepageContent();
    const titles = content.trustIndicators.map((item) => item.title);
    assert.ok(titles.includes("Secure Checkout"));
    assert.ok(titles.includes("Reliable Delivery"));
    assert.equal(
      titles.some((title) => /supplier/i.test(title)),
      false,
    );
  });
});
