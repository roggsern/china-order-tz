"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminProfitsApiError,
  fetchAdminProfitDashboard,
  type AdminProfitDashboard,
  type AdminProfitProductRow,
  type AdminProfitSupplierRow,
} from "@/lib/api/admin-profits";

function formatMoney(value: string | number, currency = "TZS"): string {
  const n = typeof value === "string" ? Number(value) : value;
  if (!Number.isFinite(n)) return `${value} ${currency}`;
  return `${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

function formatMargin(value: string | number): string {
  const n = typeof value === "string" ? Number(value) : value;
  if (!Number.isFinite(n)) return String(value);
  return `${n.toFixed(2)}%`;
}

function MetricCard({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-lg border border-zinc-800 bg-zinc-900/60 px-4 py-4">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">{label}</p>
      <p className="mt-2 text-xl font-semibold text-zinc-100">{value}</p>
    </div>
  );
}

function ProductTable({
  title,
  rows,
  empty,
}: {
  title: string;
  rows: AdminProfitProductRow[];
  empty: string;
}) {
  return (
    <section className="rounded-lg border border-zinc-800 bg-zinc-950/40">
      <header className="border-b border-zinc-800 px-4 py-3">
        <h2 className="text-sm font-semibold text-zinc-100">{title}</h2>
      </header>
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-zinc-900/80 text-[11px] uppercase tracking-wider text-zinc-500">
            <tr>
              <th className="px-4 py-2.5 font-medium">Product</th>
              <th className="px-4 py-2.5 font-medium">Revenue</th>
              <th className="px-4 py-2.5 font-medium">Cost</th>
              <th className="px-4 py-2.5 font-medium">Profit</th>
              <th className="px-4 py-2.5 font-medium">Margin</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-zinc-500">
                  {empty}
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={`${row.product_id}-${row.product_variant_id ?? "x"}`} className="border-t border-zinc-800/80">
                  <td className="px-4 py-3 text-zinc-200">
                    <div className="font-medium">{row.product_name ?? "—"}</div>
                    <div className="text-xs text-zinc-500">
                      {[row.variant_name, row.sku].filter(Boolean).join(" · ") || "—"}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-zinc-300">{formatMoney(row.revenue)}</td>
                  <td className="px-4 py-3 text-zinc-300">{formatMoney(row.total_cost)}</td>
                  <td className="px-4 py-3 text-zinc-300">{formatMoney(row.gross_profit)}</td>
                  <td className="px-4 py-3 text-zinc-300">{formatMargin(row.margin_percentage)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function SupplierTable({ rows }: { rows: AdminProfitSupplierRow[] }) {
  return (
    <section className="rounded-lg border border-zinc-800 bg-zinc-950/40">
      <header className="border-b border-zinc-800 px-4 py-3">
        <h2 className="text-sm font-semibold text-zinc-100">Supplier profitability</h2>
      </header>
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-zinc-900/80 text-[11px] uppercase tracking-wider text-zinc-500">
            <tr>
              <th className="px-4 py-2.5 font-medium">Supplier</th>
              <th className="px-4 py-2.5 font-medium">Revenue</th>
              <th className="px-4 py-2.5 font-medium">Cost</th>
              <th className="px-4 py-2.5 font-medium">Profit</th>
              <th className="px-4 py-2.5 font-medium">Margin</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-zinc-500">
                  No supplier profit data yet.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.supplier_id} className="border-t border-zinc-800/80">
                  <td className="px-4 py-3 text-zinc-200">
                    <div className="font-medium">{row.supplier_name ?? "—"}</div>
                    <div className="text-xs text-zinc-500">{row.supplier_code ?? "—"}</div>
                  </td>
                  <td className="px-4 py-3 text-zinc-300">{formatMoney(row.revenue)}</td>
                  <td className="px-4 py-3 text-zinc-300">{formatMoney(row.total_cost)}</td>
                  <td className="px-4 py-3 text-zinc-300">{formatMoney(row.gross_profit)}</td>
                  <td className="px-4 py-3 text-zinc-300">{formatMargin(row.margin_percentage)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

export function AdminProfitDashboard() {
  const [data, setData] = useState<AdminProfitDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setData(
        await fetchAdminProfitDashboard({
          from: from || undefined,
          to: to || undefined,
        }),
      );
    } catch (err) {
      setData(null);
      setError(err instanceof AdminProfitsApiError ? err.message : "Unable to load profits.");
    } finally {
      setLoading(false);
    }
  }, [from, to]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const summary = data?.summary;

  return (
    <div className="space-y-6 p-4 md:p-6">
      <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-zinc-50">Profit Dashboard</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Snapshot-based revenue, cost, and margin. Historical orders never recalculate from live prices.
          </p>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <label className="text-xs text-zinc-500">
            From
            <input
              type="date"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
            />
          </label>
          <label className="text-xs text-zinc-500">
            To
            <input
              type="date"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
            />
          </label>
          <button
            type="button"
            onClick={() => void reload()}
            className="rounded-md bg-[#c9a227] px-3 py-2 text-sm font-semibold text-zinc-950 hover:bg-[#e8c547]"
          >
            Refresh
          </button>
        </div>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/60 bg-red-950/40 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      {loading && !data ? (
        <p className="text-sm text-zinc-500">Loading profit metrics…</p>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard
              label="Revenue"
              value={summary ? formatMoney(summary.revenue, summary.currency) : "—"}
            />
            <MetricCard
              label="Cost"
              value={summary ? formatMoney(summary.total_cost, summary.currency) : "—"}
            />
            <MetricCard
              label="Gross Profit"
              value={summary ? formatMoney(summary.gross_profit, summary.currency) : "—"}
            />
            <MetricCard
              label="Margin"
              value={summary ? formatMargin(summary.margin_percentage) : "—"}
            />
          </div>

          <div className="grid gap-4 xl:grid-cols-2">
            <ProductTable
              title="Top profitable products"
              rows={data?.top_products ?? []}
              empty="No profitable product rows yet."
            />
            <ProductTable
              title="Low margin products"
              rows={data?.low_margin_products ?? []}
              empty="No low-margin products in range."
            />
          </div>

          <SupplierTable rows={data?.suppliers ?? []} />
        </>
      )}
    </div>
  );
}
