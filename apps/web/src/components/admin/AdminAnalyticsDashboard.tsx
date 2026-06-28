"use client";

import dynamic from "next/dynamic";
import { useCallback, useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import { AdminLiveIndicator } from "@/components/admin/AdminLiveIndicator";
import { AdminStatCard } from "@/components/admin/AdminStatCard";
import { AnalyticsSkeleton } from "@/components/admin/analytics/AnalyticsSkeleton";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import {
  computeAdminAnalytics,
  type AdminAnalyticsSnapshot,
  type AnalyticsRangeDays,
} from "@/lib/admin/analytics";
import { formatPrice } from "@/lib/catalog/utils";

const AnalyticsChartsSection = dynamic(
  () =>
    import("@/components/admin/analytics/AnalyticsChartsSection").then(
      (module) => module.AnalyticsChartsSection,
    ),
  {
    ssr: false,
    loading: () => (
      <div className="space-y-6">
        <div className="h-64 animate-pulse rounded-xl bg-zinc-100" />
        <div className="h-64 animate-pulse rounded-xl bg-zinc-100" />
      </div>
    ),
  },
);

const RANGE_OPTIONS: AnalyticsRangeDays[] = [7, 14, 30];

async function fetchServerAnalytics(rangeDays: AnalyticsRangeDays): Promise<AdminAnalyticsSnapshot | null> {
  try {
    const response = await fetch(`/api/admin/analytics?rangeDays=${rangeDays}`, {
      cache: "no-store",
    });
    if (!response.ok) {
      return null;
    }
    return (await response.json()) as AdminAnalyticsSnapshot;
  } catch {
    return null;
  }
}

export function AdminAnalyticsDashboard() {
  const { orders, isHydrated, refreshOrders, lastSyncedAt } = useAdminOrders();
  const [rangeDays, setRangeDays] = useState<AnalyticsRangeDays>(30);
  const [serverSnapshot, setServerSnapshot] = useState<AdminAnalyticsSnapshot | null>(null);
  const [isLoadingServer, setIsLoadingServer] = useState(true);

  const clientSnapshot = useMemo(
    () => (isHydrated ? computeAdminAnalytics(orders, rangeDays) : null),
    [isHydrated, orders, rangeDays],
  );

  const snapshot = serverSnapshot ?? clientSnapshot;

  const loadServerAnalytics = useCallback(async () => {
    setIsLoadingServer(true);
    const next = await fetchServerAnalytics(rangeDays);
    if (next) {
      setServerSnapshot(next);
    }
    setIsLoadingServer(false);
  }, [rangeDays]);

  useEffect(() => {
    void loadServerAnalytics();
  }, [loadServerAnalytics]);

  useEffect(() => {
    if (!isHydrated || !lastSyncedAt) {
      return;
    }
    void loadServerAnalytics();
  }, [isHydrated, lastSyncedAt, loadServerAnalytics]);

  const showSkeleton = !isHydrated || (!snapshot && isLoadingServer);

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="admin-page-header">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Business intelligence
          </p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">Analytics</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Real-time revenue, orders, and fulfillment insights.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <AdminLiveIndicator />

          <div className="inline-flex rounded-xl border border-zinc-200 bg-white p-1 shadow-sm">
            {RANGE_OPTIONS.map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => setRangeDays(option)}
                className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                  rangeDays === option
                    ? "bg-zinc-950 text-[#e8c547]"
                    : "text-zinc-500 hover:text-zinc-900"
                }`}
              >
                {option}d
              </button>
            ))}
          </div>

          <button type="button" onClick={() => { refreshOrders(); void loadServerAnalytics(); }} className="admin-btn-secondary">
            Refresh
          </button>
        </div>
      </div>

      {showSkeleton ? (
        <div className="mt-8">
          <AnalyticsSkeleton />
        </div>
      ) : snapshot ? (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.3 }}
          className="mt-8 space-y-8"
        >
          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <AdminStatCard label="Total orders" value={snapshot.totalOrders} variant="dark" livePulse />
            <AdminStatCard
              label="Total revenue"
              value={formatPrice(snapshot.totalRevenue)}
              sub="Paid orders only"
              isText
              accent="text-[#c9a227]"
              variant="gold"
              livePulse
            />
            <AdminStatCard label="Paid orders" value={snapshot.paidOrders} variant="default" livePulse />
            <AdminStatCard label="Pending orders" value={snapshot.pendingOrders} variant="default" livePulse />
            <AdminStatCard
              label="Failed payments"
              value={snapshot.failedPayments}
              accent="text-red-600"
              variant="dark"
              livePulse
            />
            <AdminStatCard
              label="Active users"
              value={snapshot.activeUsers7d}
              sub="Last 7 days"
              variant="gold"
              livePulse
            />
            <AdminStatCard
              label="Active deliveries"
              value={snapshot.activeDeliveries}
              sub="In logistics pipeline"
              accent="text-indigo-400"
              variant="dark"
              livePulse
            />
          </section>

          <AnalyticsChartsSection snapshot={snapshot} rangeDays={rangeDays} />
        </motion.div>
      ) : null}
    </div>
  );
}
