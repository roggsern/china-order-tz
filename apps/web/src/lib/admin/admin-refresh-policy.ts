/**
 * Admin auto-refresh policy tiers.
 *
 * Intervals (documented for ops):
 * - HIGH_ACTIVITY: 15s visible / 30s hidden — fulfilment, warehouse, orders queues
 * - MEDIUM_ACTIVITY: 30s visible / 60s hidden — command center, shipments, purchase orders, catalog health
 * - LOW_ACTIVITY: no background polling — products, settings, reports
 *
 * Orders queue uses AdminOrdersProvider WebSocket/polling (realtime-config.ts); policy
 * documents the tier but does not add duplicate polling on that page.
 */

export type AdminRefreshActivity = "HIGH_ACTIVITY" | "MEDIUM_ACTIVITY" | "LOW_ACTIVITY";

export type AdminRefreshPageKey =
  | "command_center"
  | "alerts"
  | "fulfillment_queue"
  | "warehouse_queue"
  | "orders_queue"
  | "shipments"
  | "purchase_orders"
  | "catalog_health"
  | "products"
  | "settings"
  | "reports";

export type AdminRefreshIntervalPolicy = {
  activity: AdminRefreshActivity;
  /** Poll when tab is visible; null disables polling. */
  visibleMs: number | null;
  /** Poll when tab is hidden; null disables polling. */
  hiddenMs: number | null;
};

const HIGH_ACTIVITY: AdminRefreshIntervalPolicy = {
  activity: "HIGH_ACTIVITY",
  visibleMs: 15_000,
  hiddenMs: 30_000,
};

const MEDIUM_ACTIVITY: AdminRefreshIntervalPolicy = {
  activity: "MEDIUM_ACTIVITY",
  visibleMs: 30_000,
  hiddenMs: 60_000,
};

const LOW_ACTIVITY: AdminRefreshIntervalPolicy = {
  activity: "LOW_ACTIVITY",
  visibleMs: null,
  hiddenMs: null,
};

/** Page → refresh tier mapping (single source of truth). */
export const ADMIN_PAGE_REFRESH_POLICY: Record<AdminRefreshPageKey, AdminRefreshIntervalPolicy> = {
  fulfillment_queue: HIGH_ACTIVITY,
  warehouse_queue: HIGH_ACTIVITY,
  orders_queue: HIGH_ACTIVITY,
  command_center: MEDIUM_ACTIVITY,
  alerts: MEDIUM_ACTIVITY,
  shipments: MEDIUM_ACTIVITY,
  purchase_orders: MEDIUM_ACTIVITY,
  catalog_health: MEDIUM_ACTIVITY,
  products: LOW_ACTIVITY,
  settings: LOW_ACTIVITY,
  reports: LOW_ACTIVITY,
};

export function getAdminRefreshPolicy(page: AdminRefreshPageKey): AdminRefreshIntervalPolicy {
  return ADMIN_PAGE_REFRESH_POLICY[page];
}

export function resolveAdminRefreshIntervalMs(
  page: AdminRefreshPageKey,
  hidden: boolean,
): number | null {
  const policy = getAdminRefreshPolicy(page);
  return hidden ? policy.hiddenMs : policy.visibleMs;
}

export function isAdminAutoRefreshEnabled(page: AdminRefreshPageKey): boolean {
  return resolveAdminRefreshIntervalMs(page, false) !== null;
}

export function formatAdminRefreshPolicyLabel(page: AdminRefreshPageKey): string {
  const { activity } = getAdminRefreshPolicy(page);
  if (activity === "HIGH_ACTIVITY") return "High activity · 15–30s";
  if (activity === "MEDIUM_ACTIVITY") return "Medium activity · 30–60s";
  return "Manual refresh only";
}

/** Pages that rely on AdminOrdersProvider transport instead of interval polling. */
export function usesExternalOrdersRealtime(page: AdminRefreshPageKey): boolean {
  return page === "orders_queue";
}
