"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  AdminStoreDashboardApiError,
  canViewStoreDashboard,
  fetchStoreDashboard,
  formatStoreMoney,
  type StoreDashboardData,
} from "@/lib/api/admin-store-dashboard";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

function MetricCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-4">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">{label}</p>
      <p className="mt-2 text-xl font-bold text-zinc-100">{value}</p>
    </div>
  );
}

export function AdminStoreDashboard({ storeId }: { storeId: string }) {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewStoreDashboard(permissions);

  const [data, setData] = useState<StoreDashboardData | null>(null);
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
      const dashboard = await fetchStoreDashboard(storeId);
      setData(dashboard);
    } catch (err) {
      setData(null);
      setError(err instanceof AdminStoreDashboardApiError ? err.message : "Unable to load dashboard.");
    } finally {
      setLoading(false);
    }
  }, [canView, storeId]);

  useEffect(() => {
    if (permissionsLoading) return;
    void reload();
  }, [permissionsLoading, reload]);

  if (permissionsLoading || loading) {
    return <p className="text-sm text-zinc-400">Loading store dashboard…</p>;
  }

  if (!canView) {
    return (
      <p className="text-sm text-zinc-500">
        You need <code className="text-zinc-300">stores.view</code> and store access to view this dashboard.
      </p>
    );
  }

  if (error || !data) {
    return <p className="text-sm text-red-400">{error ?? "Dashboard unavailable."}</p>;
  }

  const profit = data.profit_summary;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c9a227]">
            Store operations
          </p>
          <h1 className="mt-1 text-2xl font-semibold text-white">{data.store.name}</h1>
          <p className="mt-1 text-sm text-zinc-400">
            {data.store.code} · {data.period.from} → {data.period.to}
          </p>
        </div>
        <Link
          href={`/admin/stores/${storeId}`}
          className="rounded-md border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:border-[#c9a227]/50"
        >
          ← Store settings
        </Link>
      </header>

      <section>
        <h2 className="mb-3 text-sm font-semibold text-zinc-200">Sales summary</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard label="Gross revenue" value={formatStoreMoney(profit.gross_revenue)} />
          <MetricCard label="Orders" value={String(data.orders_count)} />
          <MetricCard label="Gross profit" value={formatStoreMoney(profit.gross_profit)} />
          <MetricCard label="Margin" value={`${profit.margin_percentage}%`} />
        </div>
      </section>

      <section>
        <h2 className="mb-3 text-sm font-semibold text-zinc-200">Inventory</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          <MetricCard label="Inventory value" value={formatStoreMoney(data.inventory_value)} />
          <MetricCard label="Units on hand" value={String(data.inventory_units)} />
          <MetricCard label="Low stock alerts" value={String(data.low_stock_alerts)} />
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-2">
        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-4">
          <h2 className="text-sm font-semibold text-zinc-200">Top products</h2>
          <ul className="mt-3 space-y-2">
            {data.top_products.length === 0 ? (
              <li className="text-sm text-zinc-500">No product sales in this period.</li>
            ) : (
              data.top_products.map((row, index) => (
                <li key={`${row.product_name ?? index}`} className="flex justify-between text-sm">
                  <span className="text-zinc-300">{row.product_name ?? "Product"}</span>
                  <span className="text-zinc-500">{formatStoreMoney(row.revenue)}</span>
                </li>
              ))
            )}
          </ul>
        </div>

        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-4">
          <h2 className="text-sm font-semibold text-zinc-200">Customers</h2>
          <ul className="mt-3 space-y-2 text-sm">
            <li className="flex justify-between">
              <span className="text-zinc-400">Walk-in</span>
              <span className="text-zinc-200">{data.customers.walk_in}</span>
            </li>
            <li className="flex justify-between">
              <span className="text-zinc-400">Registered</span>
              <span className="text-zinc-200">{data.customers.registered}</span>
            </li>
            <li className="flex justify-between">
              <span className="text-zinc-400">Returning</span>
              <span className="text-zinc-200">{data.customers.returning}</span>
            </li>
            <li className="flex justify-between">
              <span className="text-zinc-400">New</span>
              <span className="text-zinc-200">{data.customers.new}</span>
            </li>
            <li className="flex justify-between border-t border-zinc-800 pt-2">
              <span className="text-zinc-400">Team members</span>
              <span className="text-zinc-200">{data.team_count}</span>
            </li>
          </ul>
        </div>
      </section>

      <section className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-4">
        <h2 className="text-sm font-semibold text-zinc-200">Profit summary</h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          <MetricCard label="Net revenue" value={formatStoreMoney(profit.net_revenue)} />
          <MetricCard label="Refunds" value={formatStoreMoney(profit.refund_amount)} />
          <MetricCard label="Gross margin" value={`${profit.margin_percentage}%`} />
        </div>
      </section>
    </div>
  );
}
