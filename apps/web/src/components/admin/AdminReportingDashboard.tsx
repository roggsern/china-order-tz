"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ADMIN_REPORT_TYPES,
  ADMIN_REPORT_TYPE_LABELS,
  AdminReportingApiError,
  downloadAdminReport,
  fetchAdminReportingDashboard,
  type AdminReportingDashboard as AdminReportingDashboardData,
  type AdminReportType,
} from "@/lib/api/admin-reporting";
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

function formatShortDate(iso: string): string {
  try {
    return new Intl.DateTimeFormat("en-TZ", {
      month: "short",
      day: "numeric",
    }).format(new Date(`${iso}T00:00:00`));
  } catch {
    return iso.slice(5);
  }
}

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat("en-TZ", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function maxValue(values: number[]): number {
  return Math.max(...values, 1);
}

function DailyBarChart({
  data,
  valueKey,
  formatValue,
}: {
  data: Array<{ date: string; value: number }>;
  valueKey: string;
  formatValue: (n: number) => string;
}) {
  if (data.length === 0) {
    return <p className="mt-8 text-center text-sm text-zinc-500">No data for this range.</p>;
  }

  const peak = maxValue(data.map((p) => p.value));
  const step = Math.max(1, Math.ceil(data.length / 8));

  return (
    <div className="mt-6 flex h-48 items-end gap-1 sm:gap-1.5">
      {data.map((point, index) => {
        const height = `${Math.max(4, (point.value / peak) * 100)}%`;
        const showLabel = index === 0 || index === data.length - 1 || index % step === 0;
        return (
          <div key={`${valueKey}-${point.date}`} className="flex min-w-0 flex-1 flex-col items-center gap-1.5">
            <div
              title={`${formatShortDate(point.date)}: ${formatValue(point.value)}`}
              className="w-full max-w-7 rounded-t-md bg-gradient-to-t from-[#8b6914] to-[#e8c547] shadow-[0_4px_16px_rgba(201,162,39,0.2)]"
              style={{ height }}
            />
            <span className="h-3 truncate text-[9px] font-medium text-zinc-500">
              {showLabel ? formatShortDate(point.date) : ""}
            </span>
          </div>
        );
      })}
    </div>
  );
}

type AdminReportingDashboardProps = {
  title?: string;
  eyebrow?: string;
  description?: string;
};

export function AdminReportingDashboard({
  title = "Dashboard",
  eyebrow = "Reporting engine",
  description = "Live metrics, trends, and exports from the admin reporting platform.",
}: AdminReportingDashboardProps = {}) {
  const [from, setFrom] = useState(defaultFrom);
  const [to, setTo] = useState(defaultTo);
  const [appliedFrom, setAppliedFrom] = useState(defaultFrom);
  const [appliedTo, setAppliedTo] = useState(defaultTo);
  const [data, setData] = useState<AdminReportingDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exporting, setExporting] = useState<string | null>(null);

  const load = useCallback(async (rangeFrom: string, rangeTo: string) => {
    setLoading(true);
    setError(null);
    try {
      const next = await fetchAdminReportingDashboard({
        from: rangeFrom,
        to: rangeTo,
      });
      setData(next);
      setAppliedFrom(next.period.from);
      setAppliedTo(next.period.to);
      setFrom(next.period.from);
      setTo(next.period.to);
    } catch (err) {
      setData(null);
      setError(
        err instanceof AdminReportingApiError
          ? err.message
          : "Unable to load reporting dashboard.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(appliedFrom, appliedTo);
    // Initial load only — range applied via Apply button / presets.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const applyRange = () => {
    void load(from, to);
  };

  const applyPreset = (days: number) => {
    const end = defaultTo();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - (days - 1));
    const start = startDate.toISOString().slice(0, 10);
    setFrom(start);
    setTo(end);
    void load(start, end);
  };

  const revenueSeries = useMemo(
    () =>
      (data?.charts.daily_revenue ?? []).map((p) => ({
        date: p.date,
        value: Number(p.revenue) || 0,
      })),
    [data],
  );

  const ordersSeries = useMemo(
    () =>
      (data?.charts.orders_trend ?? []).map((p) => ({
        date: p.date,
        value: Number(p.count) || 0,
      })),
    [data],
  );

  const handleExport = async (type: AdminReportType, format: "csv" | "xlsx") => {
    setExporting(`${type}-${format}`);
    setError(null);
    try {
      await downloadAdminReport(type, format, {
        from: appliedFrom,
        to: appliedTo,
      });
    } catch (err) {
      setError(
        err instanceof AdminReportingApiError
          ? err.message
          : "Unable to export report.",
      );
    } finally {
      setExporting(null);
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="admin-page-header">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            {eyebrow}
          </p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">{title}</h1>
          <p className="mt-1 text-sm text-zinc-500">{description}</p>
        </div>

        <div className="flex flex-wrap items-end gap-2">
          <div className="inline-flex rounded-xl border border-zinc-200 bg-white p-1 shadow-sm">
            {[7, 14, 30].map((days) => (
              <button
                key={days}
                type="button"
                onClick={() => applyPreset(days)}
                className="rounded-lg px-3 py-1.5 text-xs font-bold text-zinc-500 transition hover:text-zinc-900"
              >
                {days}d
              </button>
            ))}
          </div>
          <label className="block text-xs font-semibold text-zinc-500">
            From
            <input
              type="date"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className="mt-1 block rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
            />
          </label>
          <label className="block text-xs font-semibold text-zinc-500">
            To
            <input
              type="date"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className="mt-1 block rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
            />
          </label>
          <button type="button" onClick={applyRange} className="admin-btn-primary">
            Apply
          </button>
          <Link href="/admin/reports" className="admin-btn-secondary">
            All reports
          </Link>
        </div>
      </div>

      {error ? (
        <div className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {loading && !data ? (
        <div className="mt-8 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="h-28 animate-pulse rounded-xl bg-zinc-100" />
            ))}
          </div>
          <div className="h-64 animate-pulse rounded-xl bg-zinc-100" />
        </div>
      ) : data ? (
        <div className={`mt-8 space-y-8 ${loading ? "opacity-60" : ""}`}>
          <section>
            <h2 className="text-sm font-bold text-zinc-900">Revenue</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <AdminStatCard
                label="Paid revenue"
                value={formatPrice(data.sales.paid_revenue)}
                isText
                accent="text-[#c9a227]"
                variant="gold"
              />
              <AdminStatCard
                label="Pending revenue"
                value={formatPrice(data.sales.pending_revenue)}
                isText
                variant="default"
              />
              <AdminStatCard
                label="Refunded"
                value={formatPrice(data.sales.refunded_revenue)}
                isText
                accent="text-red-600"
                variant="dark"
              />
              <AdminStatCard
                label="Total (paid + pending)"
                value={formatPrice(data.sales.total_revenue)}
                isText
                variant="default"
              />
            </div>
          </section>

          <section>
            <h2 className="text-sm font-bold text-zinc-900">Orders</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <AdminStatCard label="Today" value={data.orders.orders_today} variant="dark" />
              <AdminStatCard label="This week" value={data.orders.orders_this_week} />
              <AdminStatCard label="This month" value={data.orders.orders_this_month} variant="gold" />
              <AdminStatCard
                label="Total in range"
                value={data.orders.total_orders}
                href="/admin/orders"
              />
              <AdminStatCard
                label="Completed"
                value={data.orders.completed_orders}
                accent="text-emerald-600"
              />
              <AdminStatCard
                label="Cancelled"
                value={data.orders.cancelled_orders}
                accent="text-red-600"
              />
            </div>
          </section>

          <section>
            <h2 className="text-sm font-bold text-zinc-900">Operations</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <AdminStatCard
                label="Warehouse picking"
                value={data.warehouse.picking}
                sub={`Packing ${data.warehouse.packing} · Ready ${data.warehouse.ready_to_ship}`}
                href="/admin/warehouse"
                variant="dark"
              />
              <AdminStatCard
                label="Shipments in transit"
                value={data.shipments.in_transit}
                sub={`Created ${data.shipments.created} · Delivered ${data.shipments.delivered}`}
                href="/admin/shipments"
              />
              <AdminStatCard
                label="Open returns"
                value={data.returns.open}
                sub={`Refunded ${formatPrice(data.returns.refunded_amount)}`}
                href="/admin/returns"
                accent="text-amber-700"
              />
              <AdminStatCard
                label="Notifications sent"
                value={data.notifications.sent}
                sub={`Failed ${data.notifications.failed} · Pending ${data.notifications.pending}`}
                href="/admin/notifications"
                variant="gold"
              />
            </div>
          </section>

          <div className="grid gap-6 xl:grid-cols-2">
            <section className="admin-card p-5">
              <h2 className="text-sm font-bold text-zinc-900">Daily revenue</h2>
              <p className="mt-1 text-xs text-zinc-500">
                {appliedFrom} → {appliedTo}
              </p>
              <DailyBarChart
                data={revenueSeries}
                valueKey="revenue"
                formatValue={formatPrice}
              />
            </section>
            <section className="admin-card p-5">
              <h2 className="text-sm font-bold text-zinc-900">Orders trend</h2>
              <p className="mt-1 text-xs text-zinc-500">
                {appliedFrom} → {appliedTo}
              </p>
              <DailyBarChart
                data={ordersSeries}
                valueKey="orders"
                formatValue={(n) => String(n)}
              />
            </section>
          </div>

          <div className="grid gap-6 xl:grid-cols-3">
            <section className="admin-card overflow-hidden xl:col-span-2">
              <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                <h2 className="text-sm font-bold text-zinc-900">Top products</h2>
                <Link
                  href="/admin/products"
                  className="text-xs font-semibold text-[#8b6914] hover:underline"
                >
                  Catalog
                </Link>
              </div>
              {data.top_products.length === 0 ? (
                <p className="px-5 py-10 text-center text-sm text-zinc-500">
                  No product sales in this range.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[480px] text-left text-sm">
                    <thead>
                      <tr className="border-b border-zinc-100 bg-zinc-50/80">
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                          Product
                        </th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                          Qty
                        </th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                          Revenue
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-zinc-100">
                      {data.top_products.map((product) => (
                        <tr key={`${product.product_id ?? product.name}`} className="hover:bg-zinc-50/80">
                          <td className="px-4 py-3 font-medium text-zinc-900">{product.name}</td>
                          <td className="px-4 py-3 text-zinc-700">{product.quantity}</td>
                          <td className="px-4 py-3 font-semibold text-zinc-900">
                            {formatPrice(product.revenue)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <section className="admin-card overflow-hidden">
              <div className="border-b border-zinc-200 px-5 py-4">
                <h2 className="text-sm font-bold text-zinc-900">Recent activity</h2>
              </div>
              {data.recent_activity.length === 0 ? (
                <p className="px-5 py-10 text-center text-sm text-zinc-500">No recent activity.</p>
              ) : (
                <ul className="divide-y divide-zinc-100">
                  {data.recent_activity.map((item) => (
                    <li key={item.id} className="px-4 py-3">
                      <p className="text-sm font-medium text-zinc-900">
                        {item.description || item.event_type || "Activity"}
                      </p>
                      <p className="mt-0.5 text-xs text-zinc-500">
                        {[item.event_type, item.actor_type, formatWhen(item.created_at)]
                          .filter(Boolean)
                          .join(" · ")}
                      </p>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>

          <section className="admin-card p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 className="text-sm font-bold text-zinc-900">Quick exports</h2>
                <p className="mt-1 text-xs text-zinc-500">
                  Download CSV or XLSX for the selected date range.
                </p>
              </div>
              <Link href="/admin/reports" className="text-xs font-semibold text-[#8b6914] hover:underline">
                Open reports panel
              </Link>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {ADMIN_REPORT_TYPES.map((type) => (
                <div
                  key={type}
                  className="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3"
                >
                  <p className="text-sm font-semibold text-zinc-900">
                    {ADMIN_REPORT_TYPE_LABELS[type]}
                  </p>
                  <div className="mt-2 flex gap-2">
                    <button
                      type="button"
                      disabled={exporting !== null}
                      onClick={() => void handleExport(type, "csv")}
                      className="rounded-lg bg-zinc-950 px-2.5 py-1 text-[11px] font-bold text-[#e8c547] disabled:opacity-50"
                    >
                      {exporting === `${type}-csv` ? "…" : "CSV"}
                    </button>
                    <button
                      type="button"
                      disabled={exporting !== null}
                      onClick={() => void handleExport(type, "xlsx")}
                      className="rounded-lg border border-zinc-300 bg-white px-2.5 py-1 text-[11px] font-bold text-zinc-700 disabled:opacity-50"
                    >
                      {exporting === `${type}-xlsx` ? "…" : "XLSX"}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </section>
        </div>
      ) : null}
    </div>
  );
}
