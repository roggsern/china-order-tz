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
  it("shows only Order from China and Buy from TZ hero slides at launch", async () => {
    const content = await getHomepageContent();
    const types = content.heroSlides.map((slide) => slide.type);
    assert.deepEqual(types, ["china", "tz"]);
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

  it("serves premium hero imagery for launch journey slides", async () => {
    const content = await getHomepageContent();
    const china = content.heroSlides.find((slide) => slide.type === "china");
    const tz = content.heroSlides.find((slide) => slide.type === "tz");

    for (const slide of [china, tz]) {
      assert.ok(slide?.desktopImageUrl?.endsWith(".webp"));
      assert.ok(slide?.mobileImageUrl?.endsWith(".webp"));
      assert.equal(slide?.contentAlignment, "LEFT");
      assert.equal(slide?.textTheme, "LIGHT");
    }
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

  it("groups ads by placement from seed when launch ads are disabled", async () => {
    const content = await getHomepageContent();
    assert.equal(getAdsByPlacement(content.advertisements, "mid_page").length, 0);
    assert.equal(getAdsByPlacement(content.advertisements, "footer").length, 0);
    assert.equal(getAdsByPlacement(content.advertisements, "homepage_banner").length, 0);

    const raw = filterActiveScheduled(homepageContentSeed.advertisements);
    assert.ok(getAdsByPlacement(raw, "mid_page").length >= 1);
    assert.ok(getAdsByPlacement(raw, "footer").length >= 1);
  });

  it("hides trusted partner sponsors from customer homepage at launch", async () => {
    const content = await getHomepageContent();
    assert.equal(content.sponsors.length, 0);
  });
});

describe("homepage commercial content — flash deals & sections", () => {
  it("computes discount percent for flash deals", () => {
    assert.equal(discountPercent(100, 75), 25);
    assert.equal(discountPercent(0, 10), 0);
    assert.equal(discountPercent(50, 60), 0);
  });

  it("hides flash deals from customer homepage at launch", async () => {
    const content = await getHomepageContent();
    assert.equal(content.flashDeals.length, 0);
  });

  it("exposes store and collection section copy for CMS-ready UI", async () => {
    const content = await getHomepageContent();
    assert.equal(content.sections.shopByStore.title, "Shop by Store");
    assert.equal(content.sections.collections.title, "Featured Collections");
    assert.ok(Array.isArray(content.collections));
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
