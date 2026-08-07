"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
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
    <div className="admin-stat-card px-4 py-4 sm:px-5">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">{label}</p>
      <p className="mt-2 text-xl font-bold text-zinc-900">{value}</p>
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
    <section className="admin-card overflow-hidden">
      <header className="border-b border-zinc-200 px-4 py-3">
        <h2 className="text-sm font-semibold text-zinc-900">{title}</h2>
      </header>
      <div className="admin-table-scroll">
        <table className="admin-table min-w-full">
          <thead>
            <tr>
              <th>Product</th>
              <th>Revenue</th>
              <th>Cost</th>
              <th>Profit</th>
              <th>Margin</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="!p-0">
                  <AdminEmptyState title={empty} description="Adjust the date range or wait for paid orders with profit snapshots." />
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={`${row.product_id}-${row.product_variant_id ?? "x"}`}>
                  <td className="admin-table-primary">
                    <div>{row.product_name ?? "—"}</div>
                    <div className="mt-0.5 text-xs font-normal text-zinc-600">
                      {[row.variant_name, row.sku].filter(Boolean).join(" · ") || "—"}
                    </div>
                  </td>
                  <td>{formatMoney(row.revenue)}</td>
                  <td>{formatMoney(row.total_cost)}</td>
                  <td>{formatMoney(row.gross_profit)}</td>
                  <td>{formatMargin(row.margin_percentage)}</td>
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
    <section className="admin-card overflow-hidden">
      <header className="border-b border-zinc-200 px-4 py-3">
        <h2 className="text-sm font-semibold text-zinc-900">Supplier profitability</h2>
      </header>
      <div className="admin-table-scroll">
        <table className="admin-table min-w-full">
          <thead>
            <tr>
              <th>Supplier</th>
              <th>Revenue</th>
              <th>Cost</th>
              <th>Profit</th>
              <th>Margin</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="!p-0">
                  <AdminEmptyState
                    title="No supplier profit data yet"
                    description="Supplier margins appear after China import orders are paid and costed."
                  />
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.supplier_id}>
                  <td className="admin-table-primary">
                    <div>{row.supplier_name ?? "—"}</div>
                    <div className="mt-0.5 text-xs font-normal text-zinc-600">{row.supplier_code ?? "—"}</div>
                  </td>
                  <td>{formatMoney(row.revenue)}</td>
                  <td>{formatMoney(row.total_cost)}</td>
                  <td>{formatMoney(row.gross_profit)}</td>
                  <td>{formatMargin(row.margin_percentage)}</td>
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
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        eyebrow="Finance"
        title="Profit Dashboard"
        description="Snapshot-based revenue, cost, and margin. Historical orders never recalculate from live prices."
        actions={
          <div className="flex flex-wrap items-end gap-2">
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
            <button type="button" onClick={() => void reload()} className="admin-btn-primary">
              Refresh
            </button>
          </div>
        }
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {loading && !data ? (
        <p className="text-sm text-zinc-600">Loading profit metrics…</p>
      ) : (
        <>
          <div className="admin-dashboard-grid-4">
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

          <div className="grid min-w-0 gap-4 xl:grid-cols-2">
            <ProductTable
              title="Top profitable products"
              rows={data?.top_products ?? []}
              empty="No profitable product rows yet"
            />
            <ProductTable
              title="Low margin products"
              rows={data?.low_margin_products ?? []}
              empty="No low-margin products in range"
            />
          </div>

          <SupplierTable rows={data?.suppliers ?? []} />
        </>
      )}
    </div>
  );
}
