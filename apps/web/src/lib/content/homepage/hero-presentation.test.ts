import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { mapCmsHeroSlide } from "./map-cms-homepage";
import {
  heroDecorativeOverlayVisible,
  heroReadabilityOverlayClass,
  normalizeHeroContentAlignment,
  normalizeHeroTextTheme,
  resolveHeroBackgroundImages,
  resolveHeroPresentation,
  resolveHeroTextMode,
} from "./hero-presentation";
import type { HomepageHeroSlide } from "./types";

describe("hero presentation — CMS media mapping", () => {
  it("maps desktop and mobile media URLs with alt text", () => {
    const mapped = mapCmsHeroSlide(
      {
        id: "slide-1",
        headline: "Premium Import",
        subheadline: null,
        eyebrow_text: null,
        description: "Shop curated China imports",
        desktop_media: {
          id: "media-desktop",
          url: "https://cdn.example.com/hero-desktop.jpg",
          alt_text: "China import collection",
        },
        mobile_media: {
          id: "media-mobile",
          url: "https://cdn.example.com/hero-mobile.jpg",
          alt_text: "Mobile hero",
        },
        content_alignment: "CENTER",
        text_theme: "LIGHT",
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

    assert.equal(mapped.desktopImageUrl, "https://cdn.example.com/hero-desktop.jpg");
    assert.equal(mapped.mobileImageUrl, "https://cdn.example.com/hero-mobile.jpg");
    assert.equal(mapped.imageAlt, "China import collection");
  });

  it("falls back mobile image to desktop when mobile media is absent", () => {
    const mapped = mapCmsHeroSlide(
      {
        id: "slide-2",
        headline: "One art direction",
        subheadline: null,
        eyebrow_text: null,
        description: null,
        desktop_media: {
          id: "media-desktop",
          url: "https://cdn.example.com/hero-only.jpg",
          alt_text: "Shared hero art",
        },
        primary_cta: null,
        secondary_cta: null,
        position: 1,
      },
      1,
    );

    assert.equal(mapped.mobileImageUrl, "https://cdn.example.com/hero-only.jpg");
    assert.equal(mapped.imageAlt, "Shared hero art");
  });
});

describe("hero presentation — alignment and theme mapping", () => {
  it("normalizes CMS alignment values", () => {
    assert.equal(normalizeHeroContentAlignment("center"), "CENTER");
    assert.equal(normalizeHeroContentAlignment("RIGHT"), "RIGHT");
    assert.equal(normalizeHeroContentAlignment(null), "CENTER");
  });

  it("normalizes CMS text theme values", () => {
    assert.equal(normalizeHeroTextTheme("dark"), "DARK");
    assert.equal(normalizeHeroTextTheme("AUTO"), "AUTO");
    assert.equal(normalizeHeroTextTheme(undefined), "LIGHT");
  });

  it("preserves alignment and theme on mapped slides", () => {
    const mapped = mapCmsHeroSlide(
      {
        id: "slide-3",
        headline: "Aligned hero",
        subheadline: null,
        eyebrow_text: null,
        description: "Copy",
        content_alignment: "RIGHT",
        text_theme: "DARK",
        primary_cta: null,
        secondary_cta: null,
        position: 2,
      },
      2,
    );

    assert.equal(mapped.contentAlignment, "RIGHT");
    assert.equal(mapped.textTheme, "DARK");
  });
});

describe("hero presentation — runtime rendering helpers", () => {
  const imageSlide: HomepageHeroSlide = {
    id: "hero-image",
    type: "china",
    title: "Order from China",
    description: "Import directly",
    ctaLabel: "Shop",
    ctaHref: "/products?origin=china",
    desktopImageUrl: "https://cdn.example.com/desktop.jpg",
    mobileImageUrl: "https://cdn.example.com/mobile.jpg",
    contentAlignment: "CENTER",
    textTheme: "AUTO",
    backgroundClass: "bg-gradient-to-br from-zinc-950 via-zinc-900 to-[#3a1510]",
    displayStart: "2020-01-01T00:00:00.000Z",
    displayEnd: "2099-12-31T23:59:59.000Z",
    priority: 100,
    status: "active",
  };

  const gradientSlide: HomepageHeroSlide = {
    ...imageSlide,
    id: "hero-gradient",
    desktopImageUrl: null,
    mobileImageUrl: null,
    textTheme: "LIGHT",
  };

  it("detects when a slide has background imagery", () => {
    assert.equal(resolveHeroBackgroundImages(imageSlide).hasImage, true);
    assert.equal(resolveHeroBackgroundImages(gradientSlide).hasImage, false);
  });

  it("uses light copy on photo heroes and keeps decorative overlay off photos", () => {
    const presentation = resolveHeroPresentation(imageSlide);
    assert.equal(presentation.textMode, "light-text");
    assert.equal(heroDecorativeOverlayVisible(presentation.background.hasImage), false);
    assert.match(
      heroReadabilityOverlayClass(true, presentation.textMode),
      /from-black/,
    );
  });

  it("keeps gradient fallback presentation when no CMS image exists", () => {
    const presentation = resolveHeroPresentation(gradientSlide);
    assert.equal(presentation.background.hasImage, false);
    assert.equal(heroDecorativeOverlayVisible(false), true);
    assert.equal(heroReadabilityOverlayClass(false, "light-text"), "bg-transparent");
  });

  it("supports dark text theme for bright overlays", () => {
    assert.equal(resolveHeroTextMode("DARK", true), "dark-text");
    assert.match(heroReadabilityOverlayClass(true, "dark-text"), /from-white/);
  });
});
