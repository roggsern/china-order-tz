import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildPremiumJourneyHeroSlides,
  HERO_ASSET_PATHS,
} from "./hero-brand-content";

describe("hero brand content — premium journey slides", () => {
  it("uses fixed public WebP paths for easy asset replacement", () => {
    assert.equal(
      HERO_ASSET_PATHS.orderFromChina.desktop,
      "/images/hero/order-from-china-desktop.webp",
    );
    assert.equal(
      HERO_ASSET_PATHS.orderFromChina.mobile,
      "/images/hero/order-from-china-mobile.webp",
    );
    assert.equal(
      HERO_ASSET_PATHS.buyFromTz.desktop,
      "/images/hero/buy-from-tz-desktop.webp",
    );
    assert.equal(
      HERO_ASSET_PATHS.buyFromTz.mobile,
      "/images/hero/buy-from-tz-mobile.webp",
    );
  });

  it("wires premium imagery and left-aligned light text for launch journeys", () => {
    const slides = buildPremiumJourneyHeroSlides();
    const china = slides.find((slide) => slide.id === "hero-china");
    const tz = slides.find((slide) => slide.id === "hero-tz");

    assert.ok(china);
    assert.ok(tz);

    for (const slide of [china!, tz!]) {
      assert.ok(slide.desktopImageUrl?.startsWith("/images/hero/"));
      assert.ok(slide.mobileImageUrl?.startsWith("/images/hero/"));
      assert.equal(slide.contentAlignment, "LEFT");
      assert.equal(slide.textTheme, "LIGHT");
      assert.ok(slide.imageAlt && slide.imageAlt.length > 20);
      assert.ok(slide.backgroundClass?.includes("gradient"));
    }

    assert.match(china!.description ?? "", /China/i);
    assert.match(china!.description ?? "", /Tanzania/i);
    assert.match(tz!.description ?? "", /Fashion/i);
    assert.match(tz!.description ?? "", /jewelry/i);
  });

  it("preserves existing CTA labels and hrefs", () => {
    const slides = buildPremiumJourneyHeroSlides();
    const china = slides.find((slide) => slide.id === "hero-china");
    const tz = slides.find((slide) => slide.id === "hero-tz");

    assert.equal(china?.ctaLabel, "Explore China Catalog");
    assert.equal(china?.ctaHref, "/products?origin=china");
    assert.equal(china?.secondaryCtaLabel, undefined);
    assert.equal(china?.secondaryCtaHref, undefined);
    assert.equal(tz?.ctaLabel, "Explore TZ Stores");
    assert.equal(tz?.ctaHref, "/buy-from-tz");
  });
});
