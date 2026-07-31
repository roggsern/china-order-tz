"use client";

import { useEffect, useState } from "react";
import {
  fetchShippingDurations,
  getCachedShippingDurations,
} from "@/lib/shipping/durations";

/**
 * Hydrates `/shipping/durations` on the client and returns a version counter so
 * PDP components re-render when the API cache becomes available.
 */
export function useShippingDurations(): number {
  const [version, setVersion] = useState(() => (getCachedShippingDurations() ? 1 : 0));

  useEffect(() => {
    let cancelled = false;
    void fetchShippingDurations().then((payload) => {
      if (!cancelled && payload) {
        setVersion((current) => current + 1);
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return version;
}
