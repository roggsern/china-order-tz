import { cache } from "react";
import { getCmsHomepage } from "@/lib/api/cms-homepage";
import type { TzStorefrontStore } from "@/lib/api/tz-stores";
import type { Product } from "@/lib/types/catalog";
import { mapCmsHomepageResponse, mergeCmsMappedIntoSeed } from "./map-cms-homepage";
import type { HomepageCampaignMeta } from "./map-cms-homepage";
import { homepageContentSeed } from "./seed";
import { filterActiveScheduled } from "./schedule";
import type {
  HomepageAdvertisement,
  HomepageContent,
  HomepageFlashDeal,
  HomepageHeroSlide,
  HomepageSectionCopy,
  HomepageSponsor,
  AdvertisementPlacement,
} from "./types";

export type ResolvedHomepageContent = {
  heroSlides: HomepageHeroSlide[];
  advertisements: HomepageAdvertisement[];
  sponsors: HomepageSponsor[];
  flashDeals: HomepageFlashDeal[];
  collections: HomepageContent["collections"];
  whyChooseUs: HomepageContent["whyChooseUs"];
  trustIndicators: HomepageContent["trustIndicators"];
  trendingSearches: string[];
  newsletter: HomepageContent["newsletter"];
  sections: HomepageContent["sections"];
  /** CMS preferred vs seed fallback. */
  source: "cms" | "fallback";
  /** Present when an active CmsCampaign won storefront resolution. */
  campaign: HomepageCampaignMeta | null;
  /** Phase A CMS rails — when set, page prefers these over catalog fetches. */
  featuredProducts?: Product[];
  featuredProductsCopy?: HomepageSectionCopy;
  newArrivalsChina?: Product[];
  newArrivalsTz?: Product[];
  bestSellers?: Product[];
  shopByStores?: TzStorefrontStore[];
};

function resolveSeedContent(now: Date): ResolvedHomepageContent {
  const raw = homepageContentSeed;

  return {
    heroSlides: filterActiveScheduled(raw.heroSlides, now),
    advertisements: filterActiveScheduled(raw.advertisements, now),
    sponsors: filterActiveScheduled(raw.sponsors, now),
    flashDeals: filterActiveScheduled(raw.flashDeals, now),
    collections: raw.collections,
    whyChooseUs: raw.whyChooseUs,
    trustIndicators: raw.trustIndicators,
    trendingSearches: raw.trendingSearches,
    newsletter: raw.newsletter,
    sections: raw.sections,
    source: "fallback",
    campaign: null,
  };
}

/**
 * Load homepage commercial content.
 * Preferred: Laravel CMS storefront homepage API (adapter → existing props).
 * Fallback: TypeScript seed — never returns an empty homepage.
 */
export async function getHomepageContent(
  now = new Date(),
): Promise<ResolvedHomepageContent> {
  return getHomepageContentCached(now.toISOString());
}

const getHomepageContentCached = cache(async (nowIso: string): Promise<ResolvedHomepageContent> => {
  const now = new Date(nowIso);
  const seedBase = resolveSeedContent(now);

  try {
    const cms = await getCmsHomepage({
      commerceContext: "GLOBAL",
      allowGlobalFallback: true,
    });

    const mapped = mapCmsHomepageResponse(cms, seedBase);
    if (!mapped.appliedCmsSections) {
      return {
        ...seedBase,
        campaign: mapped.campaign,
        source: "fallback",
      };
    }

    return mergeCmsMappedIntoSeed(seedBase, mapped);
  } catch {
    return seedBase;
  }
});

export function getAdsByPlacement(
  advertisements: HomepageAdvertisement[],
  placement: AdvertisementPlacement,
): HomepageAdvertisement[] {
  return advertisements.filter((ad) => ad.placement === placement);
}

export function discountPercent(oldPrice: number, newPrice: number): number {
  if (oldPrice <= 0 || newPrice >= oldPrice) {
    return 0;
  }
  return Math.round(((oldPrice - newPrice) / oldPrice) * 100);
}
