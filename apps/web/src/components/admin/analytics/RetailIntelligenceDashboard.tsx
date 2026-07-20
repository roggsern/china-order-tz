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
    return <p className="mt-6 text-center text-sm text-zinc-500">No chart data.</p>;
  }

  if (chart.type === "pie") {
    const total = points.reduce((s, p) => s + Number(p.y), 0) || 1;
    return (
      <ul className="mt-4 space-y-2">
        {points.map((p) => {
          const pct = Math.round((Number(p.y) / total) * 100);
          return (
            <li key={String(p.x)} className="flex items-center gap-3 text-sm">
              <span className="w-28 truncate text-zinc-400">{p.label ?? p.x}</span>
              <div className="h-2 flex-1 overflow-hidden rounded bg-zinc-800">
                <div
                  className="h-full rounded bg-gradient-to-r from-[#8b6914] to-[#e8c547]"
                  style={{ width: `${Math.max(4, pct)}%` }}
                />
              </div>
              <span className="w-24 text-right tabular-nums text-zinc-200">
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
          <span className="truncate text-[9px] text-zinc-500">{String(p.x).slice(-5)}</span>
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
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c9a227]">
            Retail intelligence
          </p>
          <h1 className="mt-1 text-2xl font-semibold text-white">Analytics Platform</h1>
          <p className="mt-1 max-w-2xl text-sm text-zinc-400">
            Live KPIs from Orders, Payments, Profit, Inventory, Returns, Sessions, and CRM — no
            duplicate business logic.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            disabled={exporting}
            onClick={() => void onExport("csv")}
            className="rounded-md border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:border-[#c9a227]/50"
          >
            Export CSV
          </button>
          <button
            type="button"
            disabled={exporting}
            onClick={() => void onExport("xlsx")}
            className="rounded-md border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:border-[#c9a227]/50"
          >
            Export Excel
          </button>
        </div>
      </header>

      <div className="flex flex-wrap gap-2 rounded-xl border border-zinc-800 bg-zinc-950/60 p-2">
        {ANALYTICS_SECTIONS.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setSection(key)}
            className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
              section === key
                ? "bg-[#c9a227]/20 text-[#e8c547] ring-1 ring-[#c9a227]/40"
                : "text-zinc-400 hover:bg-zinc-900 hover:text-white"
            }`}
          >
            {SECTION_LABELS[key]}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-800 bg-zinc-900/40 p-4">
        <label className="text-xs text-zinc-400">
          From
          <input
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-white"
          />
        </label>
        <label className="text-xs text-zinc-400">
          To
          <input
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-white"
          />
        </label>
        <label className="text-xs text-zinc-400">
          Store
          <select
            value={storeId}
            onChange={(e) => setStoreId(e.target.value)}
            className="mt-1 block min-w-[160px] rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-white"
          >
            <option value="">All assigned</option>
            {stores.map((s) => (
              <option key={s.id} value={s.id}>
                {s.code} — {s.name}
              </option>
            ))}
          </select>
        </label>
        <button
          type="button"
          onClick={() => void load()}
          className="rounded-md bg-[#c9a227] px-3 py-1.5 text-xs font-semibold text-zinc-950"
        >
          Apply
        </button>
        <Link href="/admin/pos" className="ml-auto text-xs text-[#c9a227] hover:underline">
          Open POS →
        </Link>
      </div>

      {error && (
        <div className="rounded-lg border border-red-900/50 bg-red-950/40 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      )}

      {loading && <p className="text-sm text-zinc-500">Loading analytics…</p>}

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
                  typeof value === "number" && (key.includes("amount") || key.includes("revenue") || key.includes("profit") || key.includes("sales") || key.includes("value") || key.includes("cost"))
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
            <section
              key={chart.key}
              className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-4"
            >
              <h2 className="text-sm font-semibold text-zinc-200">{chart.label}</h2>
              <p className="text-[10px] uppercase tracking-wider text-zinc-500">{chart.type} chart</p>
              <ModularChart chart={chart} />
            </section>
          ))}
        </div>
      )}

      {!loading && tableRows.length > 0 && (
        <section className="overflow-x-auto rounded-xl border border-zinc-800">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-zinc-900 text-[11px] uppercase tracking-wide text-zinc-500">
              <tr>
                {Object.keys(tableRows[0]).map((col) => (
                  <th key={col} className="px-3 py-2 font-medium">
                    {col.replace(/_/g, " ")}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {tableRows.map((row, idx) => (
                <tr key={idx} className="border-t border-zinc-800 text-zinc-300">
                  {Object.values(row).map((cell, cidx) => (
                    <td key={cidx} className="px-3 py-2 tabular-nums">
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
        </section>
      )}

      {section === "dashboard" && (
        <div className="flex flex-wrap gap-3 text-sm">
          <button type="button" onClick={() => setSection("stores")} className="text-[#c9a227] hover:underline">
            Drill into stores
          </button>
          <button type="button" onClick={() => setSection("sessions")} className="text-[#c9a227] hover:underline">
            Drill into sessions
          </button>
          <Link href="/admin/pos/returns" className="text-[#c9a227] hover:underline">
            Open returns
          </Link>
          <Link href="/admin/reports" className="text-zinc-400 hover:underline">
            Tabular reports
          </Link>
        </div>
      )}
    </div>
  );
}
