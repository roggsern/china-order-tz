"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  type ReactNode,
} from "react";
import { usePathname } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { recordStorefrontEventDeduped } from "@/lib/api/storefront-events";
import {
  buildStorefrontEventDedupeKey,
  isStorefrontTrackingEnabled,
  shouldSkipDuplicateStorefrontEvent,
  type StorefrontEventType,
} from "@/lib/storefront/storefront-tracking";
import { useVisitorIdentity } from "@/components/storefront/VisitorIdentityProvider";

type TrackEventInput = {
  eventType: StorefrontEventType;
  path?: string;
  productId?: string;
  categoryId?: string;
  metadata?: Record<string, string | number | boolean | null>;
};

type StorefrontTrackingContextValue = {
  trackEvent: (input: TrackEventInput) => Promise<void>;
  trackPageView: (path?: string) => Promise<void>;
  trackProductView: (productId: string, input?: Omit<TrackEventInput, "eventType" | "productId">) => Promise<void>;
  trackSearch: (query: string, input?: Omit<TrackEventInput, "eventType" | "metadata">) => Promise<void>;
  trackAddToCart: (productId: string, input?: Omit<TrackEventInput, "eventType" | "productId">) => Promise<void>;
  trackCheckoutStarted: (input?: Omit<TrackEventInput, "eventType">) => Promise<void>;
  trackPaymentStarted: (input?: Omit<TrackEventInput, "eventType">) => Promise<void>;
};

const StorefrontTrackingContext = createContext<StorefrontTrackingContextValue | null>(null);

export function StorefrontTrackingProvider({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const { identity, isReady } = useVisitorIdentity();
  const lastTrackedKeyRef = useRef<string | null>(null);

  const trackEvent = useCallback(
    async (input: TrackEventInput) => {
      if (!identity || !isStorefrontTrackingEnabled(pathname)) {
        return;
      }

      const payload = {
        eventType: input.eventType,
        path: input.path ?? pathname,
        productId: input.productId,
        categoryId: input.categoryId,
        metadata: input.metadata,
      };

      const dedupeKey = buildStorefrontEventDedupeKey(payload);
      if (shouldSkipDuplicateStorefrontEvent(lastTrackedKeyRef.current, dedupeKey)) {
        return;
      }

      lastTrackedKeyRef.current = dedupeKey;

      try {
        await recordStorefrontEventDeduped(
          identity,
          payload,
          dedupeKey,
          getCustomerApiToken(),
        );
      } catch {
        lastTrackedKeyRef.current = null;
      }
    },
    [identity, pathname],
  );

  const trackPageView = useCallback(
    async (path?: string) => {
      await trackEvent({ eventType: "page_view", path: path ?? pathname });
    },
    [pathname, trackEvent],
  );

  const trackProductView = useCallback(
    async (
      productId: string,
      input?: Omit<TrackEventInput, "eventType" | "productId">,
    ) => {
      await trackEvent({
        eventType: "product_viewed",
        productId,
        path: input?.path,
        categoryId: input?.categoryId,
        metadata: input?.metadata,
      });
    },
    [trackEvent],
  );

  const trackSearch = useCallback(
    async (query: string, input?: Omit<TrackEventInput, "eventType" | "metadata">) => {
      const trimmed = query.trim();
      if (!trimmed) {
        return;
      }

      await trackEvent({
        eventType: "search_performed",
        path: input?.path,
        categoryId: input?.categoryId,
        metadata: { query: trimmed },
      });
    },
    [trackEvent],
  );

  const trackAddToCart = useCallback(
    async (
      productId: string,
      input?: Omit<TrackEventInput, "eventType" | "productId">,
    ) => {
      await trackEvent({
        eventType: "add_to_cart",
        productId,
        path: input?.path,
        categoryId: input?.categoryId,
        metadata: input?.metadata,
      });
    },
    [trackEvent],
  );

  const trackCheckoutStarted = useCallback(
    async (input?: Omit<TrackEventInput, "eventType">) => {
      await trackEvent({
        eventType: "checkout_started",
        path: input?.path ?? "/checkout",
        categoryId: input?.categoryId,
        metadata: input?.metadata,
      });
    },
    [trackEvent],
  );

  const trackPaymentStarted = useCallback(
    async (input?: Omit<TrackEventInput, "eventType">) => {
      await trackEvent({
        eventType: "payment_started",
        path: input?.path ?? "/checkout/payment",
        categoryId: input?.categoryId,
        metadata: input?.metadata,
      });
    },
    [trackEvent],
  );

  useEffect(() => {
    if (!isReady || !identity || !isStorefrontTrackingEnabled(pathname)) {
      return;
    }

    const payload = {
      eventType: "page_view" as const,
      path: pathname,
    };
    const dedupeKey = buildStorefrontEventDedupeKey(payload);

    if (shouldSkipDuplicateStorefrontEvent(lastTrackedKeyRef.current, dedupeKey)) {
      return;
    }

    lastTrackedKeyRef.current = dedupeKey;

    void recordStorefrontEventDeduped(
      identity,
      payload,
      dedupeKey,
      getCustomerApiToken(),
    ).catch(() => {
      if (lastTrackedKeyRef.current === dedupeKey) {
        lastTrackedKeyRef.current = null;
      }
    });
  }, [identity, isReady, pathname]);

  const value = useMemo<StorefrontTrackingContextValue>(
    () => ({
      trackEvent,
      trackPageView,
      trackProductView,
      trackSearch,
      trackAddToCart,
      trackCheckoutStarted,
      trackPaymentStarted,
    }),
    [trackAddToCart, trackCheckoutStarted, trackEvent, trackPageView, trackPaymentStarted, trackProductView, trackSearch],
  );

  return (
    <StorefrontTrackingContext.Provider value={value}>{children}</StorefrontTrackingContext.Provider>
  );
}

export function useStorefrontTracking(): StorefrontTrackingContextValue {
  const context = useContext(StorefrontTrackingContext);
  if (!context) {
    throw new Error("useStorefrontTracking must be used within StorefrontTrackingProvider.");
  }
  return context;
}
