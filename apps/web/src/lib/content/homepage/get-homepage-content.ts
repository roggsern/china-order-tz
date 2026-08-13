import { cache } from "react";
import { getCmsHomepage } from "@/lib/api/cms-homepage";
import type { CmsHomepageResponse } from "@/lib/api/cms-homepage";
import type { TzStorefrontStore } from "@/lib/api/tz-stores";
import type { Product } from "@/lib/types/catalog";
import { applyStorefrontLaunchPresentation } from "./apply-storefront-launch";
import {
  fetchHomepageFeaturedCollectionsFromCatalog,
  resolveHomepageFeaturedCollections,
} from "./homepage-collections";
import { mapCmsHomepageResponse, mergeCmsMappedIntoSeed } from "./map-cms-homepage";
import type { HomepageCampaignMeta } from "./map-cms-homepage";
import { homepageContentSeed } from "./seed";
import { filterActiveScheduled } from "./schedule";
import type {
  HomepageAdvertisement,
  HomepageCollection,
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

export type HomepageContentLoaderDeps = {
  /** Clock for schedule filtering. Defaults to `new Date()` once per load. */
  now?: Date;
  getCmsHomepage?: (params?: Parameters<typeof getCmsHomepage>[0]) => Promise<CmsHomepageResponse>;
  fetchFeaturedCollections?: () => Promise<HomepageCollection[]>;
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

function cmsProvidesFeaturedCollections(
  collections: HomepageCollection[] | undefined,
): boolean {
  return Boolean(collections && collections.length > 0);
}

/**
 * Single homepage content resolution (CMS + optional catalog collections).
 *
 * Critical-path design (measured):
 * - featured-collections is often multi-second; CMS may be null/cheap or slow.
 * - Start catalog fetch immediately alongside CMS (no catalog→CMS waterfall).
 * - If CMS returns authoritative featured collections, return without awaiting
 *   catalog (avoids blocking on unnecessary work when CMS wins).
 * - If CMS is null/empty of collections, await the in-flight catalog promise
 *   (wall clock ≈ max(CMS, catalog), not sum).
 *
 * Exported for focused unit tests with injected upstream mocks.
 */
export async function loadHomepageContent(
  deps: HomepageContentLoaderDeps = {},
): Promise<ResolvedHomepageContent> {
  const now = deps.now ?? new Date();
  const fetchCms = deps.getCmsHomepage ?? getCmsHomepage;
  const fetchCatalog =
    deps.fetchFeaturedCollections ?? fetchHomepageFeaturedCollectionsFromCatalog;

  const seedBase = resolveSeedContent(now);

  // Kick catalog immediately; only await when CMS cannot supply collections.
  const catalogPromise = fetchCatalog().catch(() => [] as HomepageCollection[]);

  try {
    const cms = await fetchCms({
      commerceContext: "GLOBAL",
      allowGlobalFallback: true,
    });

    const mapped = mapCmsHomepageResponse(cms, seedBase);

    if (!mapped.appliedCmsSections) {
      const catalogCollections = await catalogPromise;
      return applyStorefrontLaunchPresentation({
        ...seedBase,
        collections: resolveHomepageFeaturedCollections(undefined, catalogCollections),
        campaign: mapped.campaign,
        source: "fallback",
      });
    }

    const merged = mergeCmsMappedIntoSeed(seedBase, mapped);

    if (cmsProvidesFeaturedCollections(mapped.collections)) {
      return applyStorefrontLaunchPresentation({
        ...merged,
        collections: resolveHomepageFeaturedCollections(mapped.collections, []),
      });
    }

    const catalogCollections = await catalogPromise;
    return applyStorefrontLaunchPresentation({
      ...merged,
      collections: resolveHomepageFeaturedCollections(merged.collections, catalogCollections),
    });
  } catch {
    const catalogCollections = await catalogPromise;
    return applyStorefrontLaunchPresentation({
      ...seedBase,
      collections: resolveHomepageFeaturedCollections(undefined, catalogCollections),
    });
  }
}

/**
 * Request-scoped homepage content loader for RSC.
 * Zero-arg React `cache` identity — never keyed by a volatile timestamp.
 */
const getHomepageContentCached = cache(() => loadHomepageContent());

/**
 * Load homepage commercial content.
 * Preferred: Laravel CMS storefront homepage API (adapter → existing props).
 * Fallback: TypeScript seed — never returns an empty homepage.
 *
 * Within one RSC request, callers (Home + Suspense children) share one load via
 * React `cache`. Pass an explicit `now` only for schedule-sensitive tests; that
 * path bypasses the request cache so clocks stay under caller control.
 */
export async function getHomepageContent(
  now?: Date,
): Promise<ResolvedHomepageContent> {
  if (now !== undefined) {
    return loadHomepageContent({ now });
  }
  return getHomepageContentCached();
}

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
