import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { applyStorefrontLaunchPresentation } from "./apply-storefront-launch";
import { homepageContentSeed } from "./seed";
import { filterActiveScheduled } from "./schedule";
import type { ResolvedHomepageContent } from "./get-homepage-content";

function buildResolvedContent(now: Date): ResolvedHomepageContent {
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

describe("storefront launch presentation", () => {
  it("keeps only Order from China and Buy from TZ hero slides", () => {
    const content = applyStorefrontLaunchPresentation(
      buildResolvedContent(new Date("2026-07-20T00:00:00.000Z")),
    );

    assert.deepEqual(
      content.heroSlides.map((slide) => slide.type),
      ["china", "tz"],
    );
    assert.equal(content.heroSlides.some((slide) => slide.sponsorName === "NMB"), false);
  });

  it("hides flash deals, trusted partners, and sponsor advertisements", () => {
    const content = applyStorefrontLaunchPresentation(
      buildResolvedContent(new Date("2026-07-20T00:00:00.000Z")),
    );

    assert.equal(content.flashDeals.length, 0);
    assert.equal(content.sponsors.length, 0);
    assert.equal(content.advertisements.length, 0);
  });
});
