"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
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
    <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">{label}</p>
      <p className="mt-1.5 text-lg font-semibold text-zinc-100">{value}</p>
    </div>
  );
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
    <div className="space-y-6 p-4 md:p-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-50">Customers</h1>
        <p className="mt-1 text-sm text-zinc-500">
          CRM directory of registered customer accounts. Guest carts are not listed.
        </p>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
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
        <label className="text-xs text-zinc-500">
          Search
          <input
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
            placeholder="Code, name, email, phone, order #"
            className="mt-1 block w-64 rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          />
        </label>
        <label className="text-xs text-zinc-500">
          Status
          <select
            value={status}
            onChange={(e) => {
              setPage(1);
              setStatus(e.target.value);
            }}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="dormant">Dormant</option>
            <option value="blocked">Blocked</option>
          </select>
        </label>
        <label className="text-xs text-zinc-500">
          Sort
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          >
            <option value="registered">Registered date</option>
            <option value="spend">Lifetime spend</option>
            <option value="orders">Order count</option>
            <option value="last_order">Last order</option>
          </select>
        </label>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-md bg-[#c9a227] px-3 py-2 text-sm font-semibold text-zinc-950"
        >
          Refresh
        </button>
      </div>

      <div className="overflow-x-auto rounded-lg border border-zinc-800">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-zinc-900 text-[11px] uppercase tracking-wider text-zinc-500">
            <tr>
              <th className="px-3 py-2.5">Code</th>
              <th className="px-3 py-2.5">Name</th>
              <th className="px-3 py-2.5">Contact</th>
              <th className="px-3 py-2.5">Source</th>
              <th className="px-3 py-2.5">Orders</th>
              <th className="px-3 py-2.5">Spend</th>
              <th className="px-3 py-2.5">Last order</th>
              <th className="px-3 py-2.5">Status</th>
              <th className="px-3 py-2.5">Tags</th>
              <th className="px-3 py-2.5">Registered</th>
            </tr>
          </thead>
          <tbody>
            {loading && rows.length === 0 ? (
              <tr>
                <td colSpan={10} className="px-3 py-6 text-zinc-500">
                  Loading…
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={10} className="px-3 py-6 text-zinc-500">
                  No customers match these filters.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className="border-t border-zinc-800/80 hover:bg-zinc-900/40">
                  <td className="px-3 py-2.5">
                    <Link href={`/admin/customers/${row.id}`} className="font-medium text-[#e8c547] hover:underline">
                      {row.customer_code}
                    </Link>
                  </td>
                  <td className="px-3 py-2.5 text-zinc-200">{row.name ?? "—"}</td>
                  <td className="px-3 py-2.5 text-zinc-400">
                    <div>{row.phone || "—"}</div>
                    <div className="text-xs">{row.email || "—"}</div>
                  </td>
                  <td className="px-3 py-2.5 text-zinc-400">{row.registration_source}</td>
                  <td className="px-3 py-2.5 text-zinc-300">{row.metrics?.total_orders ?? 0}</td>
                  <td className="px-3 py-2.5 text-zinc-300">
                    {money(row.metrics?.total_spend, row.metrics?.currency)}
                  </td>
                  <td className="px-3 py-2.5 text-zinc-400">
                    {row.metrics?.last_order_at
                      ? new Date(row.metrics.last_order_at).toLocaleDateString()
                      : "—"}
                  </td>
                  <td className="px-3 py-2.5 capitalize text-zinc-300">{row.lifecycle_status}</td>
                  <td className="px-3 py-2.5 text-zinc-400">
                    {(row.tags ?? []).map((t) => t.name).join(", ") || "—"}
                  </td>
                  <td className="px-3 py-2.5 text-zinc-400">
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

      <div className="flex items-center gap-3 text-sm text-zinc-400">
        <button
          type="button"
          disabled={(meta.current_page ?? 1) <= 1}
          onClick={() => setPage((p) => Math.max(1, p - 1))}
          className="rounded border border-zinc-700 px-2 py-1 disabled:opacity-40"
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
          className="rounded border border-zinc-700 px-2 py-1 disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  );
}
