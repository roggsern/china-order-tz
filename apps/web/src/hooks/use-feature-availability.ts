"use client";

import { useEffect, useState } from "react";
import {
  DEFAULT_PUBLIC_FEATURE_FLAGS,
  mapPublicFeatureFlags,
  type PublicFeatureFlags,
  type PublicFeaturesResponse,
} from "@/lib/features/feature-availability";

export type FeatureAvailabilityState = PublicFeatureFlags & {
  isLoading: boolean;
  isReady: boolean;
};

const DEFAULT_STATE: FeatureAvailabilityState = {
  ...DEFAULT_PUBLIC_FEATURE_FLAGS,
  isLoading: true,
  isReady: false,
};

export function useFeatureAvailability(): FeatureAvailabilityState {
  const [state, setState] = useState<FeatureAvailabilityState>(DEFAULT_STATE);

  useEffect(() => {
    let cancelled = false;

    async function loadFeatures() {
      try {
        const response = await fetch("/api/features/public", { cache: "no-store" });
        if (!response.ok) {
          throw new Error("Unable to load feature flags.");
        }

        const payload = (await response.json()) as PublicFeaturesResponse;
        if (cancelled) {
          return;
        }

        const flags = mapPublicFeatureFlags(payload);
        setState({
          ...flags,
          isLoading: false,
          isReady: true,
        });
      } catch {
        if (!cancelled) {
          setState({
            ...DEFAULT_PUBLIC_FEATURE_FLAGS,
            isLoading: false,
            isReady: true,
          });
        }
      }
    }

    void loadFeatures();
    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
