"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ANALYTICS_EXPORT_TYPES,
  ANALYTICS_SECTIONS,
  downloadAnalyticsExport,
  fetchAnalyticsSection,
  type AnalyticsDashboard,
  type AnalyticsExportType,
  type AnalyticsFilters,
  type AnalyticsSection,
  type ChartPayload,
} from "@/lib/api/admin-analytics";
import { fetchPosStores, type PosStore } from "@/lib/api/admin-pos";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import { AdminStatCard } from "@/components/admin/AdminStatCard";
import { formatPrice } from "@/lib/catalog/utils";

function defaultFrom(): string {
  const d = new Date();
  d.setDate(d.getDate() - 29);
  return d.toISOString().slice(0, 10);
}

function defaultTo(): string {
  return new Date().toISOString().slice(0, 10);
}

function formatMoney(n: number | undefined): string {
  return formatPrice(Number(n ?? 0));
}

function ModularChart({ chart }: { chart: ChartPayload }) {
  const points = chart.series[0]?.points ?? [];
  if (points.length === 0) {
    return <p className="mt-6 text-center text-sm text-zinc-600">No chart data.</p>;
  }

  if (chart.type === "pie") {
    const total = points.reduce((s, p) => s + Number(p.y), 0) || 1;
    return (
      <ul className="mt-4 space-y-2">
        {points.map((p) => {
          const pct = Math.round((Number(p.y) / total) * 100);
          return (
            <li key={String(p.x)} className="flex items-center gap-3 text-sm">
              <span className="w-28 truncate text-zinc-600">{p.label ?? p.x}</span>
              <div className="h-2 flex-1 overflow-hidden rounded bg-zinc-100">
                <div
                  className="h-full rounded bg-gradient-to-r from-[#8b6914] to-[#e8c547]"
                  style={{ width: `${Math.max(4, pct)}%` }}
                />
              </div>
              <span className="w-24 text-right tabular-nums text-zinc-900">
                {typeof p.y === "number" && p.y > 1000 ? formatMoney(p.y) : `${p.y} (${pct}%)`}
              </span>
            </li>
          );
        })}
      </ul>
    );
  }

  const peak = Math.max(...points.map((p) => Number(p.y)), 1);
  return (
    <div className="mt-6 flex h-44 items-end gap-1">
      {points.map((p) => (
        <div key={String(p.x)} className="flex min-w-0 flex-1 flex-col items-center gap-1">
          <div
            title={`${p.label ?? p.x}: ${p.y}`}
            className="w-full max-w-8 rounded-t bg-gradient-to-t from-[#8b6914] to-[#e8c547]"
            style={{ height: `${Math.max(6, (Number(p.y) / peak) * 100)}%` }}
          />
          <span className="truncate text-[9px] text-zinc-600">{String(p.x).slice(-5)}</span>
        </div>
      ))}
    </div>
  );
}

const SECTION_LABELS: Record<AnalyticsSection, string> = {
  dashboard: "Executive",
  sales: "Sales",
  profit: "Financial",
  inventory: "Inventory",
  returns: "Returns",
  customers: "Customers",
  promotions: "Promotions",
  loyalty: "Loyalty",
  growth: "Growth",
  stores: "Stores",
  sessions: "Sessions",
};

function rowsFromSection(section: AnalyticsSection, data: Record<string, unknown>): Array<Record<string, unknown>> {
  if (section === "sales") return (data.top_products as Array<Record<string, unknown>>) ?? [];
  if (section === "stores") return (data.ranking as Array<Record<string, unknown>>) ?? [];
  if (section === "sessions") return (data.cashier_productivity as Array<Record<string, unknown>>) ?? [];
  if (section === "returns") return (data.by_store as Array<Record<string, unknown>>) ?? [];
  if (section === "customers") return (data.top_customers as Array<Record<string, unknown>>) ?? [];
  if (section === "promotions") return (data.top_promotions as Array<Record<string, unknown>>) ?? [];
  if (section === "inventory") return (data.highest_stock_value as Array<Record<string, unknown>>) ?? [];
  if (section === "profit") {
    const summary = (data.summary as Record<string, unknown>) ?? {};
    return Object.entries(summary).map(([metric, value]) => ({ metric, value }));
  }
  return [];
}

export function RetailIntelligenceDashboard() {
  const [section, setSection] = useState<AnalyticsSection>("dashboard");
  const [stores, setStores] = useState<PosStore[]>([]);
  const [from, setFrom] = useState(defaultFrom);
  const [to, setTo] = useState(defaultTo);
  const [storeId, setStoreId] = useState("");
  const [data, setData] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);

  const filters: AnalyticsFilters = useMemo(
    () => ({
      from,
      to,
      store_id: storeId || undefined,
      pos_only: true,
    }),
    [from, to, storeId],
  );

  useEffect(() => {
    fetchPosStores()
      .then(setStores)
      .catch(() => setStores([]));
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const payload = await fetchAnalyticsSection(section, filters);
      setData(payload as Record<string, unknown>);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load analytics");
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [section, filters]);

  useEffect(() => {
    void load();
  }, [load]);

  const exportType: AnalyticsExportType =
    section === "dashboard"
      ? "sales"
      : section === "profit"
        ? "profit"
        : (ANALYTICS_EXPORT_TYPES.includes(section as AnalyticsExportType)
            ? (section as AnalyticsExportType)
            : "sales");

  const onExport = async (format: "csv" | "xlsx") => {
    setExporting(true);
    try {
      await downloadAnalyticsExport(exportType, format, filters);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Export failed");
    } finally {
      setExporting(false);
    }
  };

  const dashboard = section === "dashboard" ? (data as AnalyticsDashboard | null) : null;
  const charts: ChartPayload[] = useMemo(() => {
    if (!data) return [];
    if (Array.isArray(data.charts)) return data.charts as ChartPayload[];
    if (data.series && typeof data.series === "object") {
      return Object.values(data.series as Record<string, ChartPayload>);
    }
    return [];
  }, [data]);

  const tableRows = data ? rowsFromSection(section, data) : [];

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        eyebrow="Retail intelligence"
        title="Analytics Platform"
        description="Live KPIs from Orders, Payments, Profit, Inventory, Returns, Sessions, and CRM — no duplicate business logic."
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href="/admin/analytics/china" className="admin-btn-secondary text-xs">
              China commercial →
            </Link>
            <button
              type="button"
              disabled={exporting}
              onClick={() => void onExport("csv")}
              className="admin-btn-secondary text-xs disabled:opacity-50"
            >
              Export CSV
            </button>
            <button
              type="button"
              disabled={exporting}
              onClick={() => void onExport("xlsx")}
              className="admin-btn-secondary text-xs disabled:opacity-50"
            >
              Export Excel
            </button>
          </div>
        }
      />

      <div className="admin-card flex flex-wrap gap-2 p-2">
        {ANALYTICS_SECTIONS.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setSection(key)}
            className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
              section === key
                ? "bg-[#c9a227]/15 text-[#8b6914] ring-1 ring-[#c9a227]/40"
                : "text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900"
            }`}
          >
            {SECTION_LABELS[key]}
          </button>
        ))}
      </div>

      <div className="admin-card flex flex-wrap items-end gap-3 p-4">
        <label className="admin-label">
          From
          <input
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            className="admin-input mt-1"
          />
        </label>
        <label className="admin-label">
          To
          <input
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            className="admin-input mt-1"
          />
        </label>
        <label className="admin-label">
          Store
          <select
            value={storeId}
            onChange={(e) => setStoreId(e.target.value)}
            className="admin-input mt-1 min-w-[160px]"
          >
            <option value="">All assigned</option>
            {stores.map((s) => (
              <option key={s.id} value={s.id}>
                {s.code} — {s.name}
              </option>
            ))}
          </select>
        </label>
        <button type="button" onClick={() => void load()} className="admin-btn-primary">
          Apply
        </button>
        <Link href="/admin/pos" className="ml-auto text-xs font-medium text-[#8b6914] hover:underline">
          Open POS →
        </Link>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      )}

      {loading && <p className="text-sm text-zinc-600">Loading analytics…</p>}

      {!loading && dashboard && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <AdminStatCard label="Today's Sales" value={formatMoney(dashboard.kpis.todays_sales)} />
          <AdminStatCard label="Today's Orders" value={String(dashboard.kpis.todays_orders ?? 0)} />
          <AdminStatCard label="Today's Profit" value={formatMoney(dashboard.kpis.todays_profit)} />
          <AdminStatCard label="Refunds" value={formatMoney(dashboard.kpis.todays_refunds)} />
          <AdminStatCard label="Returns" value={String(dashboard.kpis.todays_returns ?? 0)} />
          <AdminStatCard label="AOV" value={formatMoney(dashboard.kpis.average_order_value)} />
          <AdminStatCard label="Gross Margin" value={`${dashboard.kpis.gross_margin ?? 0}%`} />
          <AdminStatCard label="Open Sessions" value={String(dashboard.kpis.active_sessions ?? 0)} />
          <AdminStatCard label="Cash Drawers" value={String(dashboard.kpis.open_cash_drawers ?? 0)} />
          <AdminStatCard label="Low Stock" value={String(dashboard.kpis.low_stock_alerts ?? 0)} />
        </div>
      )}

      {!loading && data && section !== "dashboard" && data.summary != null && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {Object.entries(data.summary as Record<string, unknown>)
            .slice(0, 8)
            .map(([key, value]) => (
              <AdminStatCard
                key={key}
                label={key.replace(/_/g, " ")}
                value={
                  typeof value === "number" &&
                  (key.includes("amount") ||
                    key.includes("revenue") ||
                    key.includes("profit") ||
                    key.includes("sales") ||
                    key.includes("value") ||
                    key.includes("cost"))
                    ? formatMoney(value)
                    : String(value ?? "—")
                }
              />
            ))}
        </div>
      )}

      {!loading && charts.length > 0 && (
        <div className="grid gap-4 lg:grid-cols-2">
          {charts.map((chart) => (
            <section key={chart.key} className="admin-card p-4">
              <h2 className="text-sm font-semibold text-zinc-900">{chart.label}</h2>
              <p className="text-[10px] uppercase tracking-wider text-zinc-600">{chart.type} chart</p>
              <ModularChart chart={chart} />
            </section>
          ))}
        </div>
      )}

      {!loading && tableRows.length > 0 && (
        <section className="admin-card overflow-hidden">
          <div className="admin-table-scroll">
            <table className="admin-table min-w-full">
              <thead>
                <tr>
                  {Object.keys(tableRows[0]).map((col) => (
                    <th key={col}>{col.replace(/_/g, " ")}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {tableRows.map((row, idx) => (
                  <tr key={idx}>
                    {Object.values(row).map((cell, cidx) => (
                      <td key={cidx} className="tabular-nums">
                        {cell === null || cell === undefined
                          ? "—"
                          : typeof cell === "number" && cell > 100
                            ? formatMoney(cell)
                            : String(cell)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {!loading && !dashboard && tableRows.length === 0 && charts.length === 0 && !error ? (
        <AdminEmptyState title="No analytics data for this range" />
      ) : null}

      {section === "dashboard" && (
        <div className="flex flex-wrap gap-3 text-sm">
          <button
            type="button"
            onClick={() => setSection("stores")}
            className="font-medium text-[#8b6914] hover:underline"
          >
            Drill into stores
          </button>
          <button
            type="button"
            onClick={() => setSection("sessions")}
            className="font-medium text-[#8b6914] hover:underline"
          >
            Drill into sessions
          </button>
          <Link href="/admin/pos/returns" className="font-medium text-[#8b6914] hover:underline">
            Open returns
          </Link>
          <Link href="/admin/reports" className="text-zinc-600 hover:underline">
            Tabular reports
          </Link>
        </div>
      )}
    </div>
  );
}
