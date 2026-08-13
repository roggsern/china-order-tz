"use client";

import { useEffect, useState } from "react";
import {
  getChinaStorefrontMenu,
  type ChinaStorefrontMenu,
} from "@/lib/api/china-storefront";
import { createSharedAsyncCache } from "@/lib/storefront/shared-async-cache";

const CACHE_TTL_MS = 60_000;
const chinaMenuCache = createSharedAsyncCache({ ttlMs: CACHE_TTL_MS });

type State = {
  menu: ChinaStorefrontMenu | null;
  isLoading: boolean;
  error: string | null;
};

export type UseChinaStorefrontMenuOptions = {
  /** When false, no network request is started (trigger can still open with skeleton). */
  enabled?: boolean;
};

function menuKey(category?: string): string {
  const slug = category?.trim() || "__root__";
  return `china-menu:${slug}`;
}

/**
 * ORDER FROM CHINA mega payload — dedicated /china/menu owner.
 * Dedupes concurrent consumers; callers should pass enabled=false until open/hover.
 */
export function useChinaStorefrontMenu(
  category?: string,
  options?: UseChinaStorefrontMenuOptions,
): State {
  const enabled = options?.enabled ?? true;
  const key = menuKey(category);
  const [state, setState] = useState<State>({
    menu: null,
    isLoading: enabled,
    error: null,
  });

  useEffect(() => {
    if (!enabled) {
      setState((prev) => ({
        ...prev,
        isLoading: false,
      }));
      return;
    }

    let active = true;
    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    void chinaMenuCache
      .getOrFetch(key, () => getChinaStorefrontMenu(category))
      .then((menu) => {
        if (!active) return;
        setState({ menu, isLoading: false, error: null });
      })
      .catch((error: unknown) => {
        if (!active) return;
        setState((prev) => ({
          menu: prev.menu,
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load ORDER FROM CHINA.",
        }));
      });

    return () => {
      active = false;
    };
  }, [category, enabled, key]);

  return state;
}

export function __clearChinaStorefrontMenuCacheForTests(): void {
  chinaMenuCache.clear();
  chinaMenuCache.resetStats();
}

export function __getChinaStorefrontMenuCacheStatsForTests() {
  return chinaMenuCache.getStats();
}
