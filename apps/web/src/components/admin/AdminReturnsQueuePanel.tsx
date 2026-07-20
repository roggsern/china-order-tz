"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  AdminReturnsApiError,
  createAdminReturnRefund,
  fetchAdminReturn,
  fetchAdminReturns,
  updateAdminReturnStatus,
  type AdminReturnRequest,
} from "@/lib/api/admin-returns";

const STATUS_STYLES: Record<string, string> = {
  requested: "bg-amber-50 text-amber-800 ring-amber-600/20",
  approved: "bg-blue-50 text-blue-800 ring-blue-600/20",
  rejected: "bg-red-50 text-red-800 ring-red-600/20",
  inspection: "bg-sky-50 text-sky-800 ring-sky-600/20",
  completed: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/40",
};

const STATUS_ACTIONS: Record<string, Array<{ status: string; label: string }>> = {
  requested: [
    { status: "approved", label: "Approve" },
    { status: "rejected", label: "Reject" },
  ],
  approved: [{ status: "inspection", label: "Start inspection" }],
  inspection: [{ status: "completed", label: "Complete" }],
};

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function formatMoney(amount?: number | string | null, currency = "TZS"): string {
  if (amount == null || amount === "") return "—";
  const n = typeof amount === "number" ? amount : Number.parseFloat(String(amount));
  if (Number.isNaN(n)) return String(amount);
  try {
    return new Intl.NumberFormat("en-TZ", {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    }).format(n);
  } catch {
    return `${n} ${currency}`;
  }
}

export function AdminReturnsQueuePanel() {
  const [rows, setRows] = useState<AdminReturnRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<AdminReturnRequest | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [adminNotes, setAdminNotes] = useState("");
  const [refundAmount, setRefundAmount] = useState("");
  const [refundCompleted, setRefundCompleted] = useState(false);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminReturns({
        status: statusFilter === "all" ? undefined : statusFilter,
      });
      setRows(data);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminReturnsApiError ? err.message : "Unable to load returns.",
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const loadDetail = useCallback(async (id: string) => {
    setDetailLoading(true);
    setError(null);
    try {
      const row = await fetchAdminReturn(id);
      setDetail(row);
      setAdminNotes(row.admin_notes ?? "");
      const latest = row.latest_refund ?? row.refund_transactions?.[0];
      setRefundAmount(latest?.amount != null ? String(latest.amount) : "");
      setRefundCompleted(false);
    } catch (err) {
      setDetail(null);
      setError(
        err instanceof AdminReturnsApiError
          ? err.message
          : "Unable to load return details.",
      );
    } finally {
      setDetailLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      return;
    }
    void loadDetail(selectedId);
  }, [selectedId, loadDetail]);

  const counts = useMemo(
    () => ({
      total: rows.length,
      requested: rows.filter((r) => r.status === "requested").length,
      inspection: rows.filter((r) => r.status === "inspection").length,
    }),
    [rows],
  );

  const openRow = (row: AdminReturnRequest) => {
    setSelectedId(row.id);
  };

  const applyStatus = async (status: string) => {
    if (!selectedId || busy) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await updateAdminReturnStatus(selectedId, {
        status,
        admin_notes: adminNotes.trim() || null,
      });
      setDetail(updated);
      setRows((prev) => prev.map((item) => (item.id === updated.id ? updated : item)));
    } catch (err) {
      setError(
        err instanceof AdminReturnsApiError
          ? err.message
          : "Unable to update return status.",
      );
    } finally {
      setBusy(false);
    }
  };

  const submitRefund = async () => {
    if (!selectedId || busy) return;
    const amount = Number.parseFloat(refundAmount);
    if (!Number.isFinite(amount) || amount <= 0) {
      setError("Enter a valid refund amount.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await createAdminReturnRefund(selectedId, {
        amount,
        status: refundCompleted ? "completed" : undefined,
      });
      await loadDetail(selectedId);
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminReturnsApiError ? err.message : "Unable to create refund.",
      );
    } finally {
      setBusy(false);
    }
  };

  const actions = detail ? STATUS_ACTIONS[detail.status] ?? [] : [];
  const canRefund =
    detail &&
    ["approved", "inspection", "completed"].includes(detail.status) &&
    !(detail.latest_refund || (detail.refund_transactions?.length ?? 0) > 0);

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">
            Operations
          </p>
          <h1 className="mt-1 text-2xl font-bold tracking-tight text-zinc-900">
            Returns & Refunds
          </h1>
          <p className="mt-1 text-sm text-zinc-500">
            Review return requests, advance status, and issue refunds.
          </p>
        </div>
        <div className="flex flex-wrap gap-2 text-xs font-semibold text-zinc-600">
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.total} returns
          </span>
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.requested} requested
          </span>
          <span className="rounded-lg bg-white px-3 py-1.5 ring-1 ring-zinc-200">
            {counts.inspection} inspection
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
          <option value="requested">Requested</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="inspection">Inspection</option>
          <option value="completed">Completed</option>
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

      <div className="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div className="admin-card overflow-hidden">
          {loading ? (
            <div className="p-8 text-sm text-zinc-500">Loading returns…</div>
          ) : rows.length === 0 ? (
            <div className="p-8 text-center text-sm text-zinc-500">
              No return requests match these filters.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-zinc-100 bg-zinc-50/80 text-[11px] uppercase tracking-wide text-zinc-500">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Return</th>
                    <th className="px-4 py-3 font-semibold">Order</th>
                    <th className="px-4 py-3 font-semibold">Customer</th>
                    <th className="px-4 py-3 font-semibold">Reason</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th className="px-4 py-3 font-semibold">Created</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr
                      key={row.id}
                      className={`cursor-pointer border-b border-zinc-50 ${
                        selectedId === row.id ? "bg-amber-50/60" : "hover:bg-zinc-50/80"
                      }`}
                      onClick={() => openRow(row)}
                    >
                      <td className="px-4 py-3 font-mono text-xs font-semibold text-zinc-900">
                        {row.id.slice(0, 8)}…
                      </td>
                      <td className="px-4 py-3">
                        {row.order?.order_number ? (
                          <Link
                            href={`/admin/orders/${encodeURIComponent(row.order_id)}`}
                            className="font-mono text-xs font-semibold text-[#8b6914] hover:underline"
                            onClick={(e) => e.stopPropagation()}
                          >
                            {row.order.order_number}
                          </Link>
                        ) : (
                          <span className="text-zinc-400">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-zinc-700">
                        {row.customer?.name ?? "—"}
                        {row.customer?.email ? (
                          <span className="mt-0.5 block text-xs text-zinc-400">
                            {row.customer.email}
                          </span>
                        ) : null}
                      </td>
                      <td className="max-w-[180px] truncate px-4 py-3 text-zinc-600">
                        {row.reason}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset ${
                            STATUS_STYLES[row.status] ??
                            "bg-zinc-50 text-zinc-700 ring-zinc-300/40"
                          }`}
                        >
                          {row.status_label ?? row.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-500">
                        {formatWhen(row.created_at)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <aside className="h-fit rounded-xl border border-zinc-200 bg-white p-4 shadow-sm lg:sticky lg:top-6">
          <h2 className="text-sm font-semibold text-zinc-900">Return detail</h2>
          {!selectedId ? (
            <p className="mt-3 text-sm text-zinc-500">Select a return to review.</p>
          ) : detailLoading ? (
            <p className="mt-3 text-sm text-zinc-500">Loading…</p>
          ) : !detail ? (
            <p className="mt-3 text-sm text-zinc-500">Unable to load details.</p>
          ) : (
            <div className="mt-3 space-y-4 text-sm">
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                  Status
                </p>
                <span
                  className={`mt-1 inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset ${
                    STATUS_STYLES[detail.status] ??
                    "bg-zinc-50 text-zinc-700 ring-zinc-300/40"
                  }`}
                >
                  {detail.status_label ?? detail.status}
                </span>
              </div>

              <div>
                <p className="text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                  Order
                </p>
                <p className="font-mono text-zinc-800">
                  {detail.order?.order_number ?? detail.order_id}
                </p>
              </div>

              <div>
                <p className="text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                  Customer
                </p>
                <p className="text-zinc-800">
                  {detail.customer?.name ?? "—"}
                  {detail.customer?.email ? (
                    <span className="block text-xs text-zinc-500">
                      {detail.customer.email}
                    </span>
                  ) : null}
                </p>
              </div>

              <div>
                <p className="text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                  Reason
                </p>
                <p className="text-zinc-800">{detail.reason}</p>
                {detail.description ? (
                  <p className="mt-1 text-zinc-600">{detail.description}</p>
                ) : null}
                {detail.customer_notes ? (
                  <p className="mt-1 text-xs text-zinc-500">
                    Notes: {detail.customer_notes}
                  </p>
                ) : null}
              </div>

              {(detail.items?.length ?? 0) > 0 ? (
                <div>
                  <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                    Items
                  </p>
                  <ul className="space-y-2">
                    {detail.items?.map((item) => (
                      <li
                        key={item.id}
                        className="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2"
                      >
                        <p className="font-medium text-zinc-800">
                          {item.order_item?.product_name ?? item.order_item_id.slice(0, 8)}
                        </p>
                        <p className="mt-0.5 text-xs text-zinc-500">
                          Qty {item.quantity}
                          {item.reason ? ` · ${item.reason}` : ""}
                          {item.refund_amount != null
                            ? ` · ${formatMoney(item.refund_amount, detail.order?.currency)}`
                            : ""}
                        </p>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}

              <div>
                <label
                  htmlFor="admin-return-notes"
                  className="text-[11px] font-bold uppercase tracking-wide text-zinc-500"
                >
                  Admin notes
                </label>
                <textarea
                  id="admin-return-notes"
                  value={adminNotes}
                  onChange={(e) => setAdminNotes(e.target.value)}
                  rows={3}
                  className="mt-1 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm"
                  placeholder="Optional notes for this status change"
                />
              </div>

              {actions.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {actions.map((action) => (
                    <button
                      key={action.status}
                      type="button"
                      disabled={busy}
                      onClick={() => void applyStatus(action.status)}
                      className={`rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-50 ${
                        action.status === "rejected"
                          ? "border border-red-200 bg-red-50 text-red-800 hover:bg-red-100"
                          : "bg-zinc-900 text-white hover:bg-zinc-800"
                      }`}
                    >
                      {action.label}
                    </button>
                  ))}
                </div>
              ) : null}

              {(detail.latest_refund || (detail.refund_transactions?.length ?? 0) > 0) && (
                <div>
                  <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                    Refunds
                  </p>
                  <ul className="space-y-2">
                    {(detail.refund_transactions ?? (detail.latest_refund ? [detail.latest_refund] : [])).map(
                      (refund) => (
                        <li
                          key={refund.id}
                          className="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs"
                        >
                          <p className="font-semibold text-zinc-800">
                            {formatMoney(refund.amount, refund.currency ?? detail.order?.currency)}
                          </p>
                          <p className="mt-0.5 text-zinc-500">
                            {refund.status_label ?? refund.status}
                            {refund.created_at ? ` · ${formatWhen(refund.created_at)}` : ""}
                          </p>
                        </li>
                      ),
                    )}
                  </ul>
                </div>
              )}

              {canRefund ? (
                <div className="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                  <p className="text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                    Create refund
                  </p>
                  <label htmlFor="refund-amount" className="mt-2 block text-xs text-zinc-600">
                    Amount
                  </label>
                  <input
                    id="refund-amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={refundAmount}
                    onChange={(e) => setRefundAmount(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm"
                    placeholder="0.00"
                  />
                  <label className="mt-3 flex items-center gap-2 text-xs text-zinc-700">
                    <input
                      type="checkbox"
                      checked={refundCompleted}
                      onChange={(e) => setRefundCompleted(e.target.checked)}
                      className="rounded border-zinc-300"
                    />
                    Mark refund as completed
                  </label>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void submitRefund()}
                    className="mt-3 w-full rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-50"
                  >
                    {busy ? "Saving…" : "Create refund"}
                  </button>
                </div>
              ) : null}

              <p className="text-xs text-zinc-400">
                Created {formatWhen(detail.created_at)}
                {detail.completed_at ? ` · Completed ${formatWhen(detail.completed_at)}` : ""}
              </p>
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}
