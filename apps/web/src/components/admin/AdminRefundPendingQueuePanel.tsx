"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  AdminOrdersApiError,
  completeCancellationRefund,
  failCancellationRefund,
  fetchRefundPendingOrders,
  type AdminRefundPendingOrder,
} from "@/lib/api/admin-orders";

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

function refundableAmount(row: AdminRefundPendingOrder): string {
  const open = row.refund_transactions?.find((r) => r.status !== "completed" && r.status !== "failed");
  if (open?.amount != null) return String(open.amount);
  const paid = row.payments?.find((p) => p.status === "paid");
  if (paid?.amount != null) return String(paid.amount);
  return String(row.grand_total ?? row.total ?? "");
}

function enteredRefundPendingAt(row: AdminRefundPendingOrder): string | null {
  const hit = row.status_history
    ?.slice()
    .reverse()
    .find((h) => h.new_status === "refund_pending");
  return hit?.created_at ?? row.updated_at ?? null;
}

export function AdminRefundPendingQueuePanel() {
  const [rows, setRows] = useState<AdminRefundPendingOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [referenceById, setReferenceById] = useState<Record<string, string>>({});
  const [notesById, setNotesById] = useState<Record<string, string>>({});

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setRows(await fetchRefundPendingOrders());
    } catch (err) {
      setError(err instanceof AdminOrdersApiError ? err.message : "Unable to load refund queue.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const onComplete = async (row: AdminRefundPendingOrder) => {
    const reference = (referenceById[row.id] ?? "").trim();
    if (!reference) {
      setError("Refund reference / transaction note is required.");
      return;
    }
    const amount = refundableAmount(row);
    if (!window.confirm(`Confirm full manual refund of ${formatMoney(amount, row.currency)} for ${row.order_number}?`)) {
      return;
    }

    setBusyId(row.id);
    setError(null);
    try {
      await completeCancellationRefund(row.id, {
        amount,
        reference,
        notes: notesById[row.id] ?? "",
        reason: "Admin confirmed manual cancellation refund",
        confirm: true,
      });
      await reload();
    } catch (err) {
      setError(err instanceof AdminOrdersApiError ? err.message : "Refund completion failed.");
    } finally {
      setBusyId(null);
    }
  };

  const onFail = async (row: AdminRefundPendingOrder) => {
    if (!window.confirm(`Mark refund attempt failed for ${row.order_number}? Order stays refund_pending.`)) {
      return;
    }
    setBusyId(row.id);
    setError(null);
    try {
      await failCancellationRefund(row.id, {
        notes: notesById[row.id] ?? "",
        reason: "Admin marked cancellation refund failed",
      });
      await reload();
    } catch (err) {
      setError(err instanceof AdminOrdersApiError ? err.message : "Unable to fail refund.");
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section className="rounded-xl border border-amber-200 bg-amber-50/40 p-4">
      <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-zinc-900">Refund pending queue</h2>
          <p className="mt-1 text-xs text-zinc-600">
            Manual launch workflow — no automatic provider refund. Confirm after money is returned offline.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50"
        >
          Refresh
        </button>
      </div>

      {error && (
        <div className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
          {error}
        </div>
      )}

      {loading ? (
        <p className="text-xs text-zinc-500">Loading refund queue…</p>
      ) : rows.length === 0 ? (
        <p className="text-xs text-zinc-500">No orders in refund_pending.</p>
      ) : (
        <div className="space-y-3">
          {rows.map((row) => {
            const amount = refundableAmount(row);
            const paymentMethod = row.payments?.[0]?.method ?? "—";
            return (
              <article
                key={row.id}
                className="rounded-lg border border-zinc-200 bg-white p-3 shadow-sm"
              >
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <Link
                      href={`/admin/orders/${encodeURIComponent(row.id)}`}
                      className="text-sm font-semibold text-zinc-900 underline-offset-2 hover:underline"
                    >
                      {row.order_number}
                    </Link>
                    <p className="mt-1 text-xs text-zinc-600">
                      {row.user?.name ?? "Customer"} · {row.user?.email ?? "—"}
                    </p>
                    <p className="mt-1 text-xs text-zinc-500">
                      Paid {formatMoney(row.payments?.[0]?.amount ?? row.grand_total, row.currency)} ·{" "}
                      Method {paymentMethod} · Entered {formatWhen(enteredRefundPendingAt(row))}
                    </p>
                    <p className="mt-1 text-xs font-medium text-amber-800">
                      Refundable: {formatMoney(amount, row.currency)} · Status: Refund in progress
                    </p>
                  </div>
                </div>

                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                  <label className="block text-xs text-zinc-600">
                    Refund reference
                    <input
                      value={referenceById[row.id] ?? ""}
                      onChange={(e) =>
                        setReferenceById((prev) => ({ ...prev, [row.id]: e.target.value }))
                      }
                      className="mt-1 w-full rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm"
                      placeholder="Bank / M-Pesa reference"
                    />
                  </label>
                  <label className="block text-xs text-zinc-600">
                    Note / reason
                    <input
                      value={notesById[row.id] ?? ""}
                      onChange={(e) =>
                        setNotesById((prev) => ({ ...prev, [row.id]: e.target.value }))
                      }
                      className="mt-1 w-full rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm"
                      placeholder="Confirmation note"
                    />
                  </label>
                </div>

                <div className="mt-3 flex flex-wrap gap-2">
                  <button
                    type="button"
                    disabled={busyId === row.id}
                    onClick={() => void onComplete(row)}
                    className="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                  >
                    Complete refund
                  </button>
                  <button
                    type="button"
                    disabled={busyId === row.id}
                    onClick={() => void onFail(row)}
                    className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-50"
                  >
                    Mark failed
                  </button>
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}
