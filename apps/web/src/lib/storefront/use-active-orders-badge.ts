"use client";

import { useEffect, useState } from "react";
import {
  fetchCustomerDashboardSummary,
  resolveActiveOrdersBadgeCount,
} from "@/lib/api/customer-dashboard";
import { useCustomerSession } from "@/lib/customer/use-customer-session";

type CacheEntry = {
  count: number;
  fetchedAt: number;
};

let sharedCache: CacheEntry | null = null;
let inflight: Promise<number> | null = null;
const CACHE_TTL_MS = 60_000;

async function loadActiveOrderCount(): Promise<number> {
  if (sharedCache && Date.now() - sharedCache.fetchedAt < CACHE_TTL_MS) {
    return sharedCache.count;
  }

  if (inflight) {
    return inflight;
  }

  inflight = fetchCustomerDashboardSummary()
    .then((summary) => {
      const count = resolveActiveOrdersBadgeCount(summary);
      sharedCache = { count, fetchedAt: Date.now() };
      return count;
    })
    .finally(() => {
      inflight = null;
    });

  return inflight;
}

/**
 * Optional My Orders count badge. Fetches once when the customer session is ready.
 * Skipped entirely for guests. Shared in-memory cache avoids duplicate header queries.
 */
export function useActiveOrdersBadge() {
  const { isLoggedIn, isReady } = useCustomerSession();
  const [count, setCount] = useState(() =>
    isLoggedIn && sharedCache ? sharedCache.count : 0,
  );

  useEffect(() => {
    if (!isReady || !isLoggedIn) {
      setCount(0);
      return;
    }

    let cancelled = false;

    void loadActiveOrderCount()
      .then((next) => {
        if (!cancelled) {
          setCount(next);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setCount(0);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [isLoggedIn, isReady]);

  return { count, show: isLoggedIn && count > 0 };
}
