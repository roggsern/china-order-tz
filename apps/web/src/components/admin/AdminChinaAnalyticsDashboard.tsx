"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  AdminChinaAnalyticsApiError,
  canViewChinaAnalytics,
  fetchChinaAnalyticsBundle,
  formatAnalyticsMoney,
  formatAnalyticsPercent,
  type ChinaAnalyticsBundle,
} from "@/lib/api/admin-china-analytics";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import { RevenueBarChart } from "@/components/admin/analytics/AnalyticsCharts";

function MetricCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="admin-card p-4">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">{label}</p>
      <p className="mt-2 text-xl font-bold text-zinc-900">{value}</p>
    </div>
  );
}

function MarginBars({ rows, title }: { rows: { label: string; margin_percentage: string }[]; title: string }) {
  const peak = Math.max(...rows.map((r) => Number(r.margin_percentage) || 0), 1);
  return (
    <div className="admin-card p-4">
      <h3 className="text-sm font-semibold text-zinc-900">{title}</h3>
      <ul className="mt-4 space-y-3">
        {rows.length === 0 ? (
          <li className="text-sm text-zinc-500">No data for this period.</li>
        ) : (
          rows.map((row) => (
            <li key={row.label}>
              <div className="mb-1 flex justify-between text-xs">
                <span className="font-medium text-zinc-800">{row.label}</span>
                <span className="text-zinc-500">{formatAnalyticsPercent(row.margin_percentage)}</span>
              </div>
              <div className="h-2 rounded-full bg-zinc-100">
                <div
                  className="h-2 rounded-full bg-[#8b6914]"
                  style={{ width: `${Math.max(4, (Number(row.margin_percentage) / peak) * 100)}%` }}
                />
              </div>
            </li>
          ))
        )}
      </ul>
    </div>
  );
}

export function AdminChinaAnalyticsDashboard() {
  const { permissions } = useAdminPermissions();
  const canView = canViewChinaAnalytics(permissions);
  const [data, setData] = useState<ChinaAnalyticsBundle | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      setData(await fetchChinaAnalyticsBundle());
    } catch (err) {
      setError(err instanceof AdminChinaAnalyticsApiError ? err.message : "Unable to load China analytics.");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  if (!canView) {
    return (
      <div className="p-6 text-sm text-zinc-600">
        You need <code className="rounded bg-zinc-100 px-1">analytics.view</code> to view China commercial analytics.
      </div>
    );
  }

  if (loading) {
    return <div className="p-8 text-sm text-zinc-500">Loading China commercial analytics…</div>;
  }

  if (error || !data) {
    return <div className="p-8 text-sm text-red-700">{error ?? "No analytics data."}</div>;
  }

  const { overview, landedCost, suppliers, categories, shipments } = data;
  const trendChart = overview.volume_trend.map((row) => ({
    date: row.period,
    label: row.period.slice(5),
    revenue: Number(row.revenue),
    orders: row.units,
  }));

  return (
    <div className="space-y-6 p-4 sm:p-6 lg:p-8">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">Analytics</p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900">China Commercial Intelligence</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Read-only import economics from immutable cost snapshots — no pricing or inventory changes.
          </p>
        </div>
        <Link href="/admin/analytics" className="text-sm font-semibold text-[#8b6914] hover:underline">
          ← Retail analytics
        </Link>
      </header>

      <section>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Import overview</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard label="China products" value={String(overview.total_china_products)} />
          <MetricCard label="Imported qty (PO received)" value={String(overview.total_imported_quantity)} />
          <MetricCard label="Sales generated" value={formatAnalyticsMoney(overview.total_sales_generated)} />
          <MetricCard label="Gross margin" value={formatAnalyticsPercent(overview.gross_margin_percentage)} />
          <MetricCard label="Import value (supplier)" value={formatAnalyticsMoney(overview.total_import_value)} />
          <MetricCard label="Total landed cost" value={formatAnalyticsMoney(overview.total_landed_cost)} />
          <MetricCard label="Gross profit" value={formatAnalyticsMoney(overview.gross_profit)} />
          <MetricCard label="Units sold" value={String(overview.units_sold)} />
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-2">
        <div className="admin-card p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Revenue vs cost</h2>
          <ul className="mt-4 space-y-2 text-sm">
            <li className="flex justify-between"><span>Revenue</span><span>{formatAnalyticsMoney(overview.revenue_vs_cost.revenue)}</span></li>
            <li className="flex justify-between"><span>Landed cost</span><span>{formatAnalyticsMoney(overview.revenue_vs_cost.cost)}</span></li>
            <li className="flex justify-between font-semibold"><span>Gross profit</span><span>{formatAnalyticsMoney(overview.revenue_vs_cost.profit)}</span></li>
          </ul>
        </div>
        <div className="admin-card p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Import volume trend</h2>
          {trendChart.length > 0 ? <RevenueBarChart data={trendChart} /> : <p className="mt-4 text-sm text-zinc-500">No trend data yet.</p>}
        </div>
      </section>

      <section>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Landed cost</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <MetricCard label="Avg / unit" value={formatAnalyticsMoney(landedCost.average_landed_cost_per_unit)} />
          <MetricCard label="Supplier cost" value={formatAnalyticsMoney(landedCost.components.supplier_cost)} />
          <MetricCard label="Logistics & freight" value={formatAnalyticsMoney(landedCost.components.china_logistics_and_freight)} />
          <MetricCard label="Other import costs" value={formatAnalyticsMoney(landedCost.components.other_import_costs)} />
          <MetricCard label="Total landed" value={formatAnalyticsMoney(landedCost.components.total_landed_cost)} />
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-2">
        <MarginBars rows={shipments.margin_by_supplier} title="Margin by supplier" />
        <MarginBars rows={shipments.margin_by_category} title="Margin by category" />
      </section>

      <section className="grid gap-4 lg:grid-cols-2">
        <div className="admin-card overflow-hidden">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-semibold">Supplier performance</div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                <tr>
                  <th className="px-4 py-2">Supplier</th>
                  <th className="px-4 py-2">Products</th>
                  <th className="px-4 py-2">Revenue</th>
                  <th className="px-4 py-2">Profit</th>
                  <th className="px-4 py-2">Margin</th>
                </tr>
              </thead>
              <tbody>
                {suppliers.ranking.map((row) => (
                  <tr key={row.rank} className="border-t border-zinc-100">
                    <td className="px-4 py-2 font-medium">{row.supplier_name}</td>
                    <td className="px-4 py-2">{row.products_supplied}</td>
                    <td className="px-4 py-2">{formatAnalyticsMoney(row.revenue)}</td>
                    <td className="px-4 py-2">{formatAnalyticsMoney(row.gross_profit)}</td>
                    <td className="px-4 py-2">{formatAnalyticsPercent(row.margin_percentage)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="admin-card overflow-hidden">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-semibold">Category performance</div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                <tr>
                  <th className="px-4 py-2">Category</th>
                  <th className="px-4 py-2">Units</th>
                  <th className="px-4 py-2">Revenue</th>
                  <th className="px-4 py-2">Margin</th>
                </tr>
              </thead>
              <tbody>
                {categories.categories.map((row) => (
                  <tr key={row.category_name} className="border-t border-zinc-100">
                    <td className="px-4 py-2 font-medium">{row.category_name}</td>
                    <td className="px-4 py-2">{row.imported_units}</td>
                    <td className="px-4 py-2">{formatAnalyticsMoney(row.revenue)}</td>
                    <td className="px-4 py-2">{formatAnalyticsPercent(row.margin_percentage)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Shipment economics</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard label="Shipments" value={String(shipments.shipments_count)} />
          <MetricCard label="Total freight (snapshots)" value={formatAnalyticsMoney(shipments.total_freight_cost)} />
          <MetricCard label="Avg shipment cost" value={formatAnalyticsMoney(shipments.average_shipment_cost)} />
          <MetricCard label="Cost per unit" value={formatAnalyticsMoney(shipments.cost_per_unit)} />
          <MetricCard
            label="Avg transit days"
            value={shipments.average_transit_days != null ? String(shipments.average_transit_days) : "—"}
          />
        </div>
      </section>
    </div>
  );
}
