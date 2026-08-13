"use client";

import { useEffect, useState } from "react";
import { getTzStores, type TzStorefrontStore } from "@/lib/api/tz-stores";
import { createSharedAsyncCache } from "@/lib/storefront/shared-async-cache";

const CACHE_TTL_MS = 60_000;
const tzStoresCache = createSharedAsyncCache({ ttlMs: CACHE_TTL_MS });

type State = {
  stores: TzStorefrontStore[];
  isLoading: boolean;
  error: string | null;
};

export type UseTzStoresOptions = {
  enabled?: boolean;
};

/**
 * BUY FROM TZ store list — shared across mega menu + footer consumers.
 */
export function useTzStores(options?: UseTzStoresOptions): State {
  const enabled = options?.enabled ?? true;
  const [state, setState] = useState<State>({
    stores: [],
    isLoading: enabled,
    error: null,
  });

  useEffect(() => {
    if (!enabled) {
      setState((prev) => ({ ...prev, isLoading: false }));
      return;
    }

    let active = true;
    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    void tzStoresCache
      .getOrFetch("tz-stores", () => getTzStores())
      .then((stores) => {
        if (!active) return;
        setState({ stores, isLoading: false, error: null });
      })
      .catch((error: unknown) => {
        if (!active) return;
        setState({
          stores: [],
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load stores.",
        });
      });

    return () => {
      active = false;
    };
  }, [enabled]);

  return state;
}

export function __clearTzStoresCacheForTests(): void {
  tzStoresCache.clear();
  tzStoresCache.resetStats();
}

export function __getTzStoresCacheStatsForTests() {
  return tzStoresCache.getStats();
}
