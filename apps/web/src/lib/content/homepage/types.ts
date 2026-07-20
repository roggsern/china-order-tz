/**
 * Homepage commercial content contracts.
 * Preferred source: Laravel CMS storefront homepage API (via getHomepageContent adapter).
 * Fallback: seeded TypeScript module — never empty.
 */

export type AdvertisementPlacement =
  | "hero"
  | "homepage_banner"
  | "mid_page"
  | "footer";

export type AdvertisementType =
  | "china_campaign"
  | "tz_campaign"
  | "sponsor"
  | "seasonal"
  | "promotion"
  | "product"
  | "store";

export type AdvertisementStatus = "draft" | "active" | "paused" | "expired";

export type HeroSlideType = "china" | "tz" | "sponsor" | "seasonal";

export type HomepageAdvertisement = {
  id: string;
  title: string;
  subtitle?: string;
  description?: string;
  ctaLabel: string;
  targetUrl: string;
  imageUrl?: string | null;
  desktopImageUrl?: string | null;
  mobileImageUrl?: string | null;
  /** CSS gradient fallback when no image (prevents empty slides / CLS). */
  backgroundClass?: string;
  displayStart: string;
  displayEnd: string;
  priority: number;
  status: AdvertisementStatus;
  sponsorName?: string | null;
  advertisementType: AdvertisementType;
  placement: AdvertisementPlacement;
};

export type HomepageHeroSlide = {
  id: string;
  type: HeroSlideType;
  title: string;
  subtitle?: string;
  description: string;
  ctaLabel: string;
  ctaHref: string;
  secondaryCtaLabel?: string;
  secondaryCtaHref?: string;
  /** Advertisement id when the slide is driven by the ads system. */
  advertisementId?: string;
  desktopImageUrl?: string | null;
  mobileImageUrl?: string | null;
  backgroundClass: string;
  sponsorName?: string | null;
  accent?: "china" | "tz" | "gold" | "sponsor";
  displayStart: string;
  displayEnd: string;
  priority: number;
  status: AdvertisementStatus;
};

export type HomepageSponsor = {
  id: string;
  name: string;
  href: string;
  logoText: string;
  logoUrl?: string | null;
  priority: number;
  status: AdvertisementStatus;
  displayStart: string;
  displayEnd: string;
};

export type HomepageFlashDeal = {
  id: string;
  productSlug?: string;
  title: string;
  imageEmoji?: string;
  imageUrl?: string | null;
  oldPrice: number;
  newPrice: number;
  href: string;
  /** Countdown target shown in the UI. */
  endsAt: string;
  displayStart: string;
  displayEnd: string;
  origin?: "china" | "tz";
  status: AdvertisementStatus;
  priority: number;
};

export type HomepageCollection = {
  id: string;
  name: string;
  slug: string;
  description: string;
  href: string;
  icon: string;
  gradient: string;
};

export type HomepageTrustIndicator = {
  id: string;
  title: string;
  description: string;
  icon: "secure" | "delivery" | "support" | "returns" | "official" | "quality";
};

export type HomepageWhyChooseItem = {
  id: string;
  title: string;
  description: string;
  icon: "import" | "delivery" | "secure" | "quality" | "support";
};

export type HomepageSectionCopy = {
  eyebrow: string;
  title: string;
  description: string;
  viewAllLabel?: string;
  viewAllHref?: string;
};

export type HomepageNewsletterCopy = {
  title: string;
  description: string;
  placeholder: string;
  ctaLabel: string;
  successTitle: string;
  successDescription: string;
};

export type HomepageContent = {
  heroSlides: HomepageHeroSlide[];
  advertisements: HomepageAdvertisement[];
  sponsors: HomepageSponsor[];
  flashDeals: HomepageFlashDeal[];
  collections: HomepageCollection[];
  whyChooseUs: HomepageWhyChooseItem[];
  trustIndicators: HomepageTrustIndicator[];
  trendingSearches: string[];
  newsletter: HomepageNewsletterCopy;
  sections: {
    flashDeals: HomepageSectionCopy;
    newArrivals: HomepageSectionCopy;
    bestSellers: HomepageSectionCopy;
    collections: HomepageSectionCopy;
    shopByStore: HomepageSectionCopy;
    sponsors: HomepageSectionCopy;
    whyChooseUs: HomepageSectionCopy;
    trust: HomepageSectionCopy;
  };
};
