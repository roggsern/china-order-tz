"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  AdminFulfillmentApiError,
  fetchAdminFulfillments,
  updateAdminFulfillmentStatus,
  type AdminFulfillment,
} from "@/lib/api/admin-fulfillments";

const STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  processing: "bg-blue-50 text-blue-800 ring-blue-600/20",
  ready_for_shipping: "bg-indigo-50 text-indigo-800 ring-indigo-600/20",
  shipped: "bg-violet-50 text-violet-800 ring-violet-600/20",
  delivered: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/40",
};

const NEXT_STATUS: Record<string, string | null> = {
  pending: "processing",
  processing: "ready_for_shipping",
  ready_for_shipping: "shipped",
  shipped: "delivered",
  delivered: null,
  cancelled: null,
};

export function AdminFulfillmentQueuePanel() {
  const [rows, setRows] = useState<AdminFulfillment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [strategyFilter, setStrategyFilter] = useState<"all" | "local" | "china">("all");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [busyId, setBusyId] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminFulfillments({
        strategy: strategyFilter === "all" ? undefined : strategyFilter,
        status: statusFilter === "all" ? undefined : statusFilter,
      });
      setRows(data);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to load fulfillment queue.",
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter, strategyFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const counts = useMemo(() => {
    return {
      total: rows.length,
      pending: rows.filter((r) => r.status === "pending").length,
      china: rows.filter((r) => r.strategy === "china").length,
    };
  }, [rows]);

  const advance = async (row: AdminFulfillment) => {
    const next = NEXT_STATUS[row.status];
    if (!next || busyId) return;
    setBusyId(row.id);
    try {
      const updated = await updateAdminFulfillmentStatus(row.id, { status: next });
      setRows((prev) => prev.map((item) => (item.id === row.id ? updated : item)));
    } catch (err) {
      setError(
        err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to update fulfillment status.",
      );
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">
            Operations
          </p>
          <h1 className="mt-1 text-2xl font-bold tracking-tight text-zinc-900">
            Fulfillment Queue
          </h1>
          <p className="mt-1 text-sm text-zinc-500">
            Paid orders ready for local warehouse or China procurement. Shipping is a later step.
          </p>
        </div>
        <div className="flex flex-wrap gap-2 text-xs font-semibold text-zinc-600">
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.total} open
          </span>
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.pending} pending
          </span>
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.china} China
          </span>
        </div>
      </header>

      <div className="mt-5 flex flex-wrap gap-2">
        <select
          value={strategyFilter}
          onChange={(e) => setStrategyFilter(e.target.value as typeof strategyFilter)}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All strategies</option>
          <option value="local">Local warehouse</option>
          <option value="china">China procurement</option>
        </select>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All statuses</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="ready_for_shipping">Ready for shipping</option>
          <option value="shipped">Shipped</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-800 hover:border-[#c9a227]/40"
        >
          Refresh
        </button>
      </div>

      {error ? (
        <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}

      <div className="admin-card mt-5 overflow-hidden">
        {loading ? (
          <div className="p-8 text-sm text-zinc-500">Loading fulfillments…</div>
        ) : rows.length === 0 ? (
          <div className="p-8 text-center text-sm text-zinc-500">
            No fulfillments match these filters.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-zinc-100 bg-zinc-50/80 text-[11px] uppercase tracking-wide text-zinc-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">Order</th>
                  <th className="px-4 py-3 font-semibold">Customer</th>
                  <th className="px-4 py-3 font-semibold">Strategy</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3 font-semibold">Assigned</th>
                  <th className="px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => {
                  const next = NEXT_STATUS[row.status];
                  return (
                    <tr key={row.id} className="border-b border-zinc-50">
                      <td className="px-4 py-3">
                        <Link
                          href={`/admin/orders/${encodeURIComponent(row.order_id)}`}
                          className="font-mono text-sm font-semibold text-zinc-900 hover:text-[#8b6914]"
                        >
                          {row.order?.order_number ?? "—"}
                        </Link>
                      </td>
                      <td className="px-4 py-3 text-zinc-700">
                        {row.order?.customer?.name ?? "—"}
                        {row.order?.customer?.email ? (
                          <span className="mt-0.5 block text-xs text-zinc-400">
                            {row.order.customer.email}
                          </span>
                        ) : null}
                      </td>
                      <td className="px-4 py-3">
                        <span className="rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold uppercase text-zinc-700">
                          {row.strategy_label ?? row.strategy}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${
                            STATUS_STYLES[row.status] ?? "bg-zinc-50 text-zinc-700 ring-zinc-200"
                          }`}
                        >
                          {row.status_label ?? row.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-zinc-600">
                        {row.assignee?.name ?? "Unassigned"}
                      </td>
                      <td className="px-4 py-3">
                        {next ? (
                          <button
                            type="button"
                            disabled={busyId === row.id}
                            onClick={() => void advance(row)}
                            className="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-50"
                          >
                            {busyId === row.id ? "…" : `Mark ${next.replaceAll("_", " ")}`}
                          </button>
                        ) : (
                          <span className="text-xs text-zinc-400">Done</span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
