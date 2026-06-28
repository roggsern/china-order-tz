import {
  computeAdminAnalytics,
  toAnalyticsSummary,
  type AdminAnalyticsSnapshot,
  type AdminAnalyticsSummary,
  type AnalyticsRangeDays,
} from "@/lib/admin/analytics";
import { listServerOrders } from "@/lib/admin/server/order-event-hub";
import {
  broadcastAdminOrderEvent,
  publishAdminOrderRedisEvent,
} from "@/lib/admin/server/order-ws-broadcast";

const CACHE_TTL_MS = 30_000;

type CacheEntry = {
  expiresAt: number;
  rangeDays: AnalyticsRangeDays;
  snapshot: AdminAnalyticsSnapshot;
};

declare global {
  var __chinaOrderTzAnalyticsCache: Map<AnalyticsRangeDays, CacheEntry> | undefined;
}

function getCacheStore(): Map<AnalyticsRangeDays, CacheEntry> {
  if (!globalThis.__chinaOrderTzAnalyticsCache) {
    globalThis.__chinaOrderTzAnalyticsCache = new Map();
  }
  return globalThis.__chinaOrderTzAnalyticsCache;
}

export function invalidateAnalyticsCache(): void {
  getCacheStore().clear();
}

export async function getAnalyticsSnapshot(
  rangeDays: AnalyticsRangeDays = 30,
): Promise<AdminAnalyticsSnapshot> {
  const cache = getCacheStore();
  const cached = cache.get(rangeDays);

  if (cached && cached.expiresAt > Date.now()) {
    return cached.snapshot;
  }

  const orders = await listServerOrders();
  const snapshot = computeAdminAnalytics(orders, rangeDays);
  cache.set(rangeDays, {
    snapshot,
    rangeDays,
    expiresAt: Date.now() + CACHE_TTL_MS,
  });

  return snapshot;
}

export async function publishAnalyticsUpdate(
  rangeDays: AnalyticsRangeDays = 30,
): Promise<AdminAnalyticsSummary> {
  invalidateAnalyticsCache();
  const snapshot = await getAnalyticsSnapshot(rangeDays);
  const summary = toAnalyticsSummary(snapshot);

  const event = {
    type: "analytics_update" as const,
    summary,
    rangeDays,
  };

  broadcastAdminOrderEvent(event);
  void publishAdminOrderRedisEvent(event);

  return summary;
}
