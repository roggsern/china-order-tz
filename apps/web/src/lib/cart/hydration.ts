import { isAdminPath, isPostCheckoutPath } from "@/lib/checkout/routes";

export type CartHydrationPlan = {
  /** Load cart from localStorage and mark the provider hydrated on first paint. */
  markHydratedImmediately: boolean;
  /** Validate against catalog and sync with the server cart after hydration. */
  runBackgroundSync: boolean;
};

/**
 * Cart pages must render from localStorage immediately. Background sync may refine
 * items afterward but must never block `isHydrated`.
 */
export function buildCartHydrationPlan(pathname: string): CartHydrationPlan {
  if (isAdminPath(pathname) || isPostCheckoutPath(pathname)) {
    return {
      markHydratedImmediately: true,
      runBackgroundSync: false,
    };
  }

  return {
    markHydratedImmediately: true,
    runBackgroundSync: true,
  };
}
