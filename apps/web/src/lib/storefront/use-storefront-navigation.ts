"use client";

import { useEffect, useState } from "react";
import {
  getCmsNavigation,
  isCmsNavigationAllPayload,
  type CmsNavigationAllPayload,
} from "@/lib/api/cms-navigation";
import type { StorefrontNavAudience } from "@/lib/storefront/navigation-policy";
import {
  mapAudienceToCmsAudience,
  resolveStorefrontNavigation,
  type ResolvedStorefrontNavigation,
} from "@/lib/storefront/resolve-storefront-navigation";
import { createSharedAsyncCache } from "@/lib/storefront/shared-async-cache";

const CACHE_TTL_MS = 60_000;
const navigationCache = createSharedAsyncCache({ ttlMs: CACHE_TTL_MS });

type State = {
  navigation: ResolvedStorefrontNavigation;
  isLoading: boolean;
  error: string | null;
};

function fallbackState(audience: StorefrontNavAudience): ResolvedStorefrontNavigation {
  return resolveStorefrontNavigation(null, audience);
}

function cacheKey(cmsAudience: string): string {
  return `nav:GLOBAL:${cmsAudience}:hydrate=0`;
}

/**
 * Shared storefront navigation resolver (Desktop + Mobile + Footer).
 *
 * hydrate_mega_menus=0: CMS China mega hydrate only returns categories (not brands /
 * featured products). MegaMenu owns /china/menu for full panel data — avoid paying
 * for unused CMS mega hydration on every concurrent nav consumer.
 */
export function useStorefrontNavigation(audience: StorefrontNavAudience): State {
  const cmsAudience = mapAudienceToCmsAudience(audience);
  const key = cacheKey(cmsAudience);

  const [payload, setPayload] = useState<CmsNavigationAllPayload | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setIsLoading(true);

    void navigationCache
      .getOrFetch(key, async () => {
        const data = await getCmsNavigation({
          commerceContext: "GLOBAL",
          audience: cmsAudience,
          hydrateMegaMenus: false,
        });
        return isCmsNavigationAllPayload(data) ? data : null;
      })
      .then((all) => {
        if (!active) return;
        setPayload(all);
        setError(null);
        setIsLoading(false);
      })
      .catch((err: unknown) => {
        if (!active) return;
        setPayload(null);
        setError(err instanceof Error ? err.message : "Navigation unavailable.");
        setIsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [cmsAudience, key]);

  const navigation = resolveStorefrontNavigation(payload, audience);
  const resolved = isLoading && !payload ? fallbackState(audience) : navigation;

  return {
    navigation: resolved,
    isLoading,
    error,
  };
}

/** Test helper — clear module cache between unit tests if needed. */
export function __clearStorefrontNavigationCacheForTests(): void {
  navigationCache.clear();
  navigationCache.resetStats();
}

export function __getStorefrontNavigationCacheStatsForTests() {
  return navigationCache.getStats();
}
