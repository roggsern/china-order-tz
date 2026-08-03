import type { HomepageHeroSlide } from "./types";

/** Stable public paths — swap WebP files in place without code changes. */
export const HERO_ASSET_PATHS = {
  orderFromChina: {
    desktop: "/images/hero/order-from-china-desktop.webp",
    mobile: "/images/hero/order-from-china-mobile.webp",
  },
  buyFromTz: {
    desktop: "/images/hero/buy-from-tz-desktop.webp",
    mobile: "/images/hero/buy-from-tz-mobile.webp",
  },
} as const;

const FAR_FUTURE = "2099-12-31T23:59:59.000Z";
const FAR_PAST = "2020-01-01T00:00:00.000Z";

/**
 * Premium dual-journey hero slides — CHINA ORDER TZ hybrid brand identity.
 * Presentation/content only; CTAs and slide IDs preserved for CMS parity.
 */
export function buildPremiumJourneyHeroSlides(): Pick<
  HomepageHeroSlide,
  | "id"
  | "type"
  | "title"
  | "subtitle"
  | "description"
  | "ctaLabel"
  | "ctaHref"
  | "secondaryCtaLabel"
  | "secondaryCtaHref"
  | "desktopImageUrl"
  | "mobileImageUrl"
  | "imageAlt"
  | "contentAlignment"
  | "textTheme"
  | "backgroundClass"
  | "accent"
  | "displayStart"
  | "displayEnd"
  | "priority"
  | "status"
>[] {
  return [
    {
      id: "hero-china",
      type: "china",
      title: "Order from China",
      subtitle: "Global Import · China to Tanzania",
      description:
        "Premium products sourced in China — air and sea logistics, warehouse-ready fulfillment, and trusted delivery to Tanzania.",
      ctaLabel: "Explore China Catalog",
      ctaHref: "/products?origin=china",
      secondaryCtaLabel: "How it works",
      secondaryCtaHref: "/#why-choose-us",
      desktopImageUrl: HERO_ASSET_PATHS.orderFromChina.desktop,
      mobileImageUrl: HERO_ASSET_PATHS.orderFromChina.mobile,
      imageAlt:
        "Luxury international logistics scene with cargo shipping, air freight, and premium branded packages on a China to Tanzania import journey",
      contentAlignment: "LEFT",
      textTheme: "LIGHT",
      backgroundClass:
        "bg-gradient-to-br from-zinc-950 via-[#070b14] to-[#1a1208]",
      accent: "china",
      displayStart: FAR_PAST,
      displayEnd: FAR_FUTURE,
      priority: 100,
      status: "active",
    },
    {
      id: "hero-tz",
      type: "tz",
      title: "Buy from TZ",
      subtitle: "Curated Tanzanian Marketplace",
      description:
        "Fashion, beauty, jewelry, accessories, and lifestyle — trusted local stores with premium boutique delivery across Tanzania.",
      ctaLabel: "Explore TZ Stores",
      ctaHref: "/buy-from-tz",
      desktopImageUrl: HERO_ASSET_PATHS.buyFromTz.desktop,
      mobileImageUrl: HERO_ASSET_PATHS.buyFromTz.mobile,
      imageAlt:
        "Warm luxury boutique marketplace with fashion, beauty, jewelry, accessories, and lifestyle products in an elegant Tanzanian retail setting",
      contentAlignment: "LEFT",
      textTheme: "LIGHT",
      backgroundClass:
        "bg-gradient-to-br from-[#2a2418] via-[#1a1814] to-[#0f1a14]",
      accent: "tz",
      displayStart: FAR_PAST,
      displayEnd: FAR_FUTURE,
      priority: 90,
      status: "active",
    },
  ];
}
