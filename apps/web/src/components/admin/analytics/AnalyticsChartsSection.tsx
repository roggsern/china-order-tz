"use client";

import {
  RevenueBarChart,
  SalesTrendChart,
  StatusPieChart,
} from "@/components/admin/analytics/AnalyticsCharts";
import type { AdminAnalyticsSnapshot, AnalyticsRangeDays } from "@/lib/admin/analytics";

type AnalyticsChartsSectionProps = {
  snapshot: AdminAnalyticsSnapshot;
  rangeDays: AnalyticsRangeDays;
};

export function AnalyticsChartsSection({ snapshot, rangeDays }: AnalyticsChartsSectionProps) {
  return (
    <>
      <section className="grid gap-6 xl:grid-cols-2">
        <article className="admin-card overflow-hidden border-zinc-200 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-5 text-zinc-100 sm:p-6">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-sm font-bold uppercase tracking-[0.14em] text-[#c9a227]">
                Daily sales trend
              </h2>
              <p className="mt-1 text-xs text-zinc-400">Paid revenue over the last {rangeDays} days</p>
            </div>
          </div>
          <SalesTrendChart data={snapshot.dailySales} />
        </article>

        <article className="admin-card overflow-hidden border-zinc-200 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-5 text-zinc-100 sm:p-6">
          <h2 className="text-sm font-bold uppercase tracking-[0.14em] text-[#c9a227]">
            Revenue per day
          </h2>
          <p className="mt-1 text-xs text-zinc-400">Bar view of daily paid revenue</p>
          <RevenueBarChart data={snapshot.revenueByDay} />
        </article>
      </section>

      <section className="admin-card overflow-hidden border-zinc-200 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-5 sm:p-6">
        <h2 className="text-sm font-bold uppercase tracking-[0.14em] text-[#c9a227]">
          Order status distribution
        </h2>
        <p className="mt-1 text-xs text-zinc-400">Fulfillment and payment lifecycle breakdown</p>
        <StatusPieChart data={snapshot.statusDistribution} />
      </section>
    </>
  );
}
