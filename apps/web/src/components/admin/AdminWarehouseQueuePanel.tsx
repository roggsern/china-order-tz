"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  AdminWarehouseApiError,
  assignAdminWarehousePacker,
  assignAdminWarehousePicker,
  fetchAdminWarehouseJobs,
  updateAdminWarehouseStatus,
  type AdminWarehouseJob,
} from "@/lib/api/admin-warehouse";

const STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  picking: "bg-blue-50 text-blue-800 ring-blue-600/20",
  picked: "bg-sky-50 text-sky-800 ring-sky-600/20",
  packing: "bg-indigo-50 text-indigo-800 ring-indigo-600/20",
  packed: "bg-violet-50 text-violet-800 ring-violet-600/20",
  ready_to_ship: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/40",
};

export function AdminWarehouseQueuePanel() {
  const [rows, setRows] = useState<AdminWarehouseJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [busyId, setBusyId] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminWarehouseJobs({
        status: statusFilter === "all" ? undefined : statusFilter,
      });
      setRows(data);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminWarehouseApiError
          ? err.message
          : "Unable to load warehouse queue.",
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const counts = useMemo(
    () => ({
      total: rows.length,
      pending: rows.filter((r) => r.status === "pending").length,
      ready: rows.filter((r) => r.status === "ready_to_ship").length,
    }),
    [rows],
  );

  const advance = async (row: AdminWarehouseJob) => {
    const next = row.next_status;
    if (!next || busyId) return;
    setBusyId(row.id);
    setError(null);
    try {
      const updated = await updateAdminWarehouseStatus(row.id, { status: next });
      setRows((prev) => prev.map((item) => (item.id === row.id ? updated : item)));
    } catch (err) {
      setError(
        err instanceof AdminWarehouseApiError
          ? err.message
          : "Unable to update warehouse status.",
      );
    } finally {
      setBusyId(null);
    }
  };

  const assignPicker = async (row: AdminWarehouseJob) => {
    if (busyId) return;
    setBusyId(row.id);
    setError(null);
    try {
      const updated = await assignAdminWarehousePicker(row.id);
      setRows((prev) => prev.map((item) => (item.id === row.id ? updated : item)));
    } catch (err) {
      setError(
        err instanceof AdminWarehouseApiError ? err.message : "Unable to assign picker.",
      );
    } finally {
      setBusyId(null);
    }
  };

  const assignPacker = async (row: AdminWarehouseJob) => {
    if (busyId) return;
    setBusyId(row.id);
    setError(null);
    try {
      const updated = await assignAdminWarehousePacker(row.id);
      setRows((prev) => prev.map((item) => (item.id === row.id ? updated : item)));
    } catch (err) {
      setError(
        err instanceof AdminWarehouseApiError ? err.message : "Unable to assign packer.",
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
            Warehouse Queue
          </h1>
          <p className="mt-1 text-sm text-zinc-500">
            Pick and pack after payment. Shipments require ready_to_ship.
          </p>
        </div>
        <div className="flex flex-wrap gap-2 text-xs font-semibold text-zinc-600">
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.total} jobs
          </span>
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.pending} pending
          </span>
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.ready} ready
          </span>
        </div>
      </header>

      <div className="mt-5 flex flex-wrap gap-2">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All statuses</option>
          <option value="pending">Pending</option>
          <option value="picking">Picking</option>
          <option value="picked">Picked</option>
          <option value="packing">Packing</option>
          <option value="packed">Packed</option>
          <option value="ready_to_ship">Ready to ship</option>
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
        <p
          className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      <div className="admin-card mt-5 overflow-hidden">
        {loading ? (
          <div className="p-8 text-sm text-zinc-500">Loading warehouse jobs…</div>
        ) : rows.length === 0 ? (
          <div className="p-8 text-center text-sm text-zinc-500">
            No warehouse jobs match these filters.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-zinc-100 bg-zinc-50/80 text-[11px] uppercase tracking-wide text-zinc-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">Job</th>
                  <th className="px-4 py-3 font-semibold">Order</th>
                  <th className="px-4 py-3 font-semibold">Customer</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3 font-semibold">Picker</th>
                  <th className="px-4 py-3 font-semibold">Packer</th>
                  <th className="px-4 py-3 font-semibold">Created</th>
                  <th className="px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-zinc-50">
                    <td className="px-4 py-3 font-mono text-xs font-semibold text-zinc-900">
                      {row.job_number}
                    </td>
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
                      <span
                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${
                          STATUS_STYLES[row.status] ??
                          "bg-zinc-50 text-zinc-700 ring-zinc-200"
                        }`}
                      >
                        {row.status_label ?? row.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-zinc-600">
                      {row.picker?.name ?? "—"}
                    </td>
                    <td className="px-4 py-3 text-zinc-600">
                      {row.packer?.name ?? "—"}
                    </td>
                    <td className="px-4 py-3 text-xs text-zinc-500">
                      {row.created_at
                        ? new Date(row.created_at).toLocaleString()
                        : "—"}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1.5">
                        {!row.picker_id &&
                        row.status !== "cancelled" &&
                        row.status !== "ready_to_ship" ? (
                          <button
                            type="button"
                            disabled={busyId === row.id}
                            onClick={() => void assignPicker(row)}
                            className="rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-semibold text-zinc-800 hover:border-[#c9a227]/40 disabled:opacity-50"
                          >
                            Assign picker
                          </button>
                        ) : null}
                        {!row.packer_id &&
                        ["packing", "packed", "picked", "picking"].includes(row.status) ? (
                          <button
                            type="button"
                            disabled={busyId === row.id}
                            onClick={() => void assignPacker(row)}
                            className="rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-semibold text-zinc-800 hover:border-[#c9a227]/40 disabled:opacity-50"
                          >
                            Assign packer
                          </button>
                        ) : null}
                        {row.next_status ? (
                          <button
                            type="button"
                            disabled={busyId === row.id}
                            onClick={() => void advance(row)}
                            className="rounded-lg bg-zinc-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-50"
                          >
                            {busyId === row.id
                              ? "…"
                              : `→ ${row.next_status.replaceAll("_", " ")}`}
                          </button>
                        ) : (
                          <span className="text-xs text-zinc-400">Done</span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
