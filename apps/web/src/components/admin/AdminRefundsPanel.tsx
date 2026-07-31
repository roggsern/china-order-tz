"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  AdminRefundsApiError,
  approveAdminRefund,
  canApproveAdminRefunds,
  canManageAdminRefunds,
  canViewAdminRefunds,
  fetchAdminRefunds,
  processAdminRefund,
  refundStatusBadgeClass,
  refundStatusLabel,
  rejectAdminRefund,
  type AdminRefundRecord,
} from "@/lib/api/admin-refunds";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "requested", label: "Requested" },
  { value: "pending", label: "Pending (legacy)" },
  { value: "under_review", label: "Under review" },
  { value: "approved", label: "Approved" },
  { value: "processing", label: "Processing" },
  { value: "completed", label: "Completed" },
  { value: "failed", label: "Failed" },
  { value: "rejected", label: "Rejected" },
];

function formatMoney(amount?: number | string | null, currency = "TZS"): string {
  if (amount == null || amount === "") return "—";
  const n = typeof amount === "number" ? amount : Number.parseFloat(String(amount));
  if (Number.isNaN(n)) return String(amount);
  try {
    return new Intl.NumberFormat("en-TZ", { style: "currency", currency, maximumFractionDigits: 0 }).format(n);
  } catch {
    return `${n} ${currency}`;
  }
}

function statusBadgeClass(status: string): string {
  return refundStatusBadgeClass(status);
}

export function AdminRefundsPanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewAdminRefunds(permissions);
  const canApprove = canApproveAdminRefunds(permissions);
  const canManage = canManageAdminRefunds(permissions);

  const [rows, setRows] = useState<AdminRefundRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState("");
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [actionNotes, setActionNotes] = useState("");
  const [busyId, setBusyId] = useState<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  const reload = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const result = await fetchAdminRefunds({
        status: statusFilter || undefined,
        search: debouncedSearch || undefined,
        perPage: 50,
      });
      setRows(result.data);
    } catch (err) {
      setError(err instanceof AdminRefundsApiError ? err.message : "Unable to load refunds.");
    } finally {
      setLoading(false);
    }
  }, [canView, debouncedSearch, statusFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const selected = useMemo(
    () => rows.find((row) => row.id === selectedId) ?? null,
    [rows, selectedId],
  );

  const runAction = async (action: "approve" | "reject" | "process", row: AdminRefundRecord) => {
    setBusyId(row.id);
    setError(null);
    try {
      if (action === "approve") {
        await approveAdminRefund(row.id, actionNotes || undefined);
      } else if (action === "reject") {
        if (!rejectReason.trim()) {
          setError("Rejection reason is required.");
          return;
        }
        await rejectAdminRefund(row.id, rejectReason.trim());
      } else {
        await processAdminRefund(row.id, actionNotes || undefined);
      }
      setActionNotes("");
      setRejectReason("");
      await reload();
    } catch (err) {
      setError(err instanceof AdminRefundsApiError ? err.message : "Refund action failed.");
    } finally {
      setBusyId(null);
    }
  };

  if (!canView) {
    return (
      <div className="admin-card p-5 text-sm text-zinc-600">
        You do not have permission to view refund operations.
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="admin-card p-4">
        <div className="flex flex-wrap gap-3">
          <input
            type="search"
            className="admin-input min-w-[200px] flex-1"
            placeholder="Search order number, reference, reason…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
          >
            {STATUS_OPTIONS.map((option) => (
              <option key={option.value || "all"} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{error}</div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <div className="admin-card overflow-hidden">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-semibold text-zinc-900">
            Refund queue
          </div>
          {loading ? (
            <p className="px-4 py-6 text-sm text-zinc-500">Loading refunds…</p>
          ) : rows.length === 0 ? (
            <p className="px-4 py-6 text-sm text-zinc-500">No refunds match your filters.</p>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {rows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    className={`flex w-full flex-col gap-1 px-4 py-3 text-left hover:bg-zinc-50 ${
                      selectedId === row.id ? "bg-zinc-50" : ""
                    }`}
                    onClick={() => setSelectedId(row.id)}
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <span className="font-medium text-zinc-900">
                        {formatMoney(row.amount, row.currency)}
                      </span>
                      <span className={`rounded px-2 py-0.5 text-[11px] font-semibold uppercase ${statusBadgeClass(row.status)}`}>
                        {row.status_label ?? refundStatusLabel(row.status)}
                      </span>
                    </div>
                    <span className="text-xs text-zinc-500">
                      Order {row.order?.order_number ?? row.order_id} ·{" "}
                      {row.order?.customer?.name ?? row.customer?.name ?? "Customer"}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="admin-card p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Refund details</h2>
          {!selected ? (
            <p className="mt-3 text-sm text-zinc-500">Select a refund to review details and actions.</p>
          ) : (
            <div className="mt-3 space-y-3 text-sm">
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Amount</p>
                <p className="font-semibold text-zinc-900">
                  {formatMoney(selected.amount, selected.currency)}
                </p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Status</p>
                <p>{selected.status_label ?? refundStatusLabel(selected.status)}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Order</p>
                {selected.order_id ? (
                  <Link href={`/admin/orders/${selected.order_id}`} className="text-[#8b6914] hover:underline">
                    {selected.order?.order_number ?? selected.order_id}
                  </Link>
                ) : (
                  "—"
                )}
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Customer</p>
                <p>{selected.order?.customer?.name ?? selected.customer?.name ?? "—"}</p>
                <p className="text-xs text-zinc-500">
                  {selected.order?.customer?.email ?? selected.customer?.email ?? ""}
                </p>
              </div>
              {selected.reason ? (
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-500">Reason</p>
                  <p>{selected.reason}</p>
                </div>
              ) : null}
              {selected.reference || selected.provider_reference ? (
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-500">Reference</p>
                  <p>{selected.provider_reference ?? selected.reference}</p>
                </div>
              ) : null}

              {!["completed", "failed", "rejected"].includes(selected.status) ? (
                <div className="space-y-2 border-t border-zinc-200 pt-3">
                  <textarea
                    className="admin-input min-h-[72px] w-full text-sm"
                    placeholder="Notes (optional for approve/process)"
                    value={actionNotes}
                    onChange={(event) => setActionNotes(event.target.value)}
                  />
                  {canApprove ? (
                    <textarea
                      className="admin-input min-h-[56px] w-full text-sm"
                      placeholder="Rejection reason (required to reject)"
                      value={rejectReason}
                      onChange={(event) => setRejectReason(event.target.value)}
                    />
                  ) : null}
                  <div className="flex flex-wrap gap-2">
                    {canApprove ? (
                      <button
                        type="button"
                        className="admin-btn-secondary text-xs"
                        disabled={busyId === selected.id}
                        onClick={() => void runAction("approve", selected)}
                      >
                        Approve
                      </button>
                    ) : null}
                    {canApprove ? (
                      <button
                        type="button"
                        className="admin-btn-secondary text-xs"
                        disabled={busyId === selected.id}
                        onClick={() => void runAction("reject", selected)}
                      >
                        Reject
                      </button>
                    ) : null}
                    {canManage ? (
                      <button
                        type="button"
                        className="admin-btn-primary text-xs"
                        disabled={busyId === selected.id}
                        onClick={() => void runAction("process", selected)}
                      >
                        Process refund
                      </button>
                    ) : null}
                  </div>
                </div>
              ) : null}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
