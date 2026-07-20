"use client";

import { useEffect, useMemo, useState } from "react";
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

type CacheEntry = {
  audience: string;
  expiresAt: number;
  payload: CmsNavigationAllPayload | null;
};

const CACHE_TTL_MS = 60_000;
let memoryCache: CacheEntry | null = null;

type State = {
  navigation: ResolvedStorefrontNavigation;
  isLoading: boolean;
  error: string | null;
};

function fallbackState(audience: StorefrontNavAudience): ResolvedStorefrontNavigation {
  return resolveStorefrontNavigation(null, audience);
}

/**
 * Shared storefront navigation resolver (Desktop + Mobile + Footer).
 * Prefers CMS API; falls back to navigation-policy / home-data.
 */
export function useStorefrontNavigation(audience: StorefrontNavAudience): State {
  const cmsAudience = mapAudienceToCmsAudience(audience);
  const [payload, setPayload] = useState<CmsNavigationAllPayload | null>(() => {
    if (
      memoryCache &&
      memoryCache.audience === cmsAudience &&
      memoryCache.expiresAt > Date.now()
    ) {
      return memoryCache.payload;
    }
    return null;
  });
  const [isLoading, setIsLoading] = useState(() => {
    if (
      memoryCache &&
      memoryCache.audience === cmsAudience &&
      memoryCache.expiresAt > Date.now()
    ) {
      return false;
    }
    return true;
  });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;

    if (
      memoryCache &&
      memoryCache.audience === cmsAudience &&
      memoryCache.expiresAt > Date.now()
    ) {
      setPayload(memoryCache.payload);
      setIsLoading(false);
      setError(null);
      return;
    }

    setIsLoading(true);

    void getCmsNavigation({
      commerceContext: "GLOBAL",
      audience: cmsAudience,
      hydrateMegaMenus: true,
    })
      .then((data) => {
        if (!active) return;
        const all = isCmsNavigationAllPayload(data) ? data : null;
        memoryCache = {
          audience: cmsAudience,
          expiresAt: Date.now() + CACHE_TTL_MS,
          payload: all,
        };
        setPayload(all);
        setError(null);
        setIsLoading(false);
      })
      .catch((err: unknown) => {
        if (!active) return;
        memoryCache = {
          audience: cmsAudience,
          expiresAt: Date.now() + CACHE_TTL_MS,
          payload: null,
        };
        setPayload(null);
        setError(err instanceof Error ? err.message : "Navigation unavailable.");
        setIsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [cmsAudience]);

  const navigation = useMemo(
    () => resolveStorefrontNavigation(payload, audience),
    [payload, audience],
  );

  // While loading with no cache, still show policy fallback (never empty nav).
  const resolved =
    isLoading && !payload ? fallbackState(audience) : navigation;

  return {
    navigation: resolved,
    isLoading,
    error,
  };
}

/** Test helper — clear module cache between unit tests if needed. */
export function __clearStorefrontNavigationCacheForTests(): void {
  memoryCache = null;
}
