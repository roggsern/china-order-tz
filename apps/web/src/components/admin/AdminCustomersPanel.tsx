"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import {
  AdminCrmApiError,
  fetchAdminCustomerSummary,
  fetchAdminCustomers,
  type AdminCustomer,
  type AdminCustomerSummary,
} from "@/lib/api/admin-crm";

function money(value?: string | number | null, currency = "TZS"): string {
  const n = typeof value === "string" ? Number(value) : value ?? 0;
  if (!Number.isFinite(n)) return `—`;
  return `${n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency}`;
}

function Card({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="admin-stat-card px-4 py-4 sm:px-5">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">{label}</p>
      <p className="mt-2 text-xl font-bold text-zinc-900">{value}</p>
    </div>
  );
}

function statusBadgeClass(status: string): string {
  if (status === "active") return "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200";
  if (status === "dormant") return "bg-amber-50 text-amber-800 ring-1 ring-amber-200";
  if (status === "blocked") return "bg-red-50 text-red-800 ring-1 ring-red-200";
  return "bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200";
}

export function AdminCustomersPanel() {
  const [summary, setSummary] = useState<AdminCustomerSummary | null>(null);
  const [rows, setRows] = useState<AdminCustomer[]>([]);
  const [meta, setMeta] = useState<{ current_page?: number; last_page?: number; total?: number }>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [sort, setSort] = useState("registered");
  const [page, setPage] = useState(1);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [sum, list] = await Promise.all([
        fetchAdminCustomerSummary(),
        fetchAdminCustomers({
          search: search || undefined,
          lifecycle_status: status || undefined,
          sort,
          direction: "desc",
          page,
          per_page: 20,
        }),
      ]);
      setSummary(sum);
      setRows(list.data);
      setMeta(list.meta ?? {});
    } catch (err) {
      setError(err instanceof AdminCrmApiError ? err.message : "Unable to load customers.");
    } finally {
      setLoading(false);
    }
  }, [search, status, sort, page]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Customers"
        description="CRM directory of registered customer accounts. Guest carts are not listed."
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Card label="Total registered" value={summary?.total_customers ?? "—"} />
        <Card label="New today" value={summary?.new_customers_today ?? "—"} />
        <Card label="New this month" value={summary?.new_customers_this_month ?? "—"} />
        <Card label="Active" value={summary?.active_customers ?? "—"} />
        <Card label="Dormant" value={summary?.dormant_customers ?? "—"} />
        <Card label="Blocked" value={summary?.blocked_customers ?? "—"} />
        <Card label="With purchases" value={summary?.customers_with_purchases ?? "—"} />
        <Card
          label="Lifetime spend"
          value={summary ? money(summary.total_lifetime_spend, summary.currency) : "—"}
        />
      </div>

      <div className="flex flex-wrap items-end gap-2">
        <label className="admin-label">
          Search
          <input
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
            placeholder="Code, name, email, phone, order #"
            className="admin-input mt-1 w-64"
          />
        </label>
        <label className="admin-label">
          Status
          <select
            value={status}
            onChange={(e) => {
              setPage(1);
              setStatus(e.target.value);
            }}
            className="admin-input mt-1"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="dormant">Dormant</option>
            <option value="blocked">Blocked</option>
          </select>
        </label>
        <label className="admin-label">
          Sort
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="admin-input mt-1"
          >
            <option value="registered">Registered date</option>
            <option value="spend">Lifetime spend</option>
            <option value="orders">Order count</option>
            <option value="last_order">Last order</option>
          </select>
        </label>
        <button type="button" onClick={() => void reload()} className="admin-btn-primary">
          Refresh
        </button>
      </div>

      <div className="admin-card overflow-hidden">
        <div className="admin-table-scroll">
          <table className="admin-table min-w-full">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Source</th>
                <th>Orders</th>
                <th>Spend</th>
                <th>Last order</th>
                <th>Status</th>
                <th>Tags</th>
                <th>Registered</th>
              </tr>
            </thead>
            <tbody>
              {loading && rows.length === 0 ? (
                <tr>
                  <td colSpan={10} className="text-zinc-600">
                    Loading…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={10} className="!p-0">
                    <AdminEmptyState title="No customers match these filters" />
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id}>
                    <td className="admin-table-primary">
                      <Link
                        href={`/admin/customers/${row.id}`}
                        className="font-medium text-[#8b6914] hover:underline"
                      >
                        {row.customer_code}
                      </Link>
                    </td>
                    <td className="text-zinc-900">{row.name ?? "—"}</td>
                    <td className="text-zinc-600">
                      <div>{row.phone || "—"}</div>
                      <div className="text-xs">{row.email || "—"}</div>
                    </td>
                    <td className="text-zinc-600">{row.registration_source}</td>
                    <td>{row.metrics?.total_orders ?? 0}</td>
                    <td>{money(row.metrics?.total_spend, row.metrics?.currency)}</td>
                    <td className="text-zinc-600">
                      {row.metrics?.last_order_at
                        ? new Date(row.metrics.last_order_at).toLocaleDateString()
                        : "—"}
                    </td>
                    <td>
                      <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadgeClass(row.lifecycle_status)}`}
                      >
                        {row.lifecycle_status}
                      </span>
                    </td>
                    <td className="text-zinc-600">
                      {(row.tags ?? []).map((t) => t.name).join(", ") || "—"}
                    </td>
                    <td className="text-zinc-600">
                      {row.registered_at || row.created_at
                        ? new Date(row.registered_at || row.created_at || "").toLocaleDateString()
                        : "—"}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="flex items-center gap-3 text-sm text-zinc-600">
        <button
          type="button"
          disabled={(meta.current_page ?? 1) <= 1}
          onClick={() => setPage((p) => Math.max(1, p - 1))}
          className="admin-btn-secondary disabled:opacity-40"
        >
          Prev
        </button>
        <span>
          Page {meta.current_page ?? 1} / {meta.last_page ?? 1} ({meta.total ?? 0} total)
        </span>
        <button
          type="button"
          disabled={(meta.current_page ?? 1) >= (meta.last_page ?? 1)}
          onClick={() => setPage((p) => p + 1)}
          className="admin-btn-secondary disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  );
}
