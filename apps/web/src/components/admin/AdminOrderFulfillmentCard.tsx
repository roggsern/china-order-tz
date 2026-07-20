"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  AdminFulfillmentApiError,
  createAdminFulfillment,
  fetchAdminFulfillments,
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

interface AdminOrderFulfillmentCardProps {
  orderId: string;
  orderStatus?: string;
}

export function AdminOrderFulfillmentCard({
  orderId,
  orderStatus,
}: AdminOrderFulfillmentCardProps) {
  const [fulfillment, setFulfillment] = useState<AdminFulfillment | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const rows = await fetchAdminFulfillments({ orderId });
      setFulfillment(rows[0] ?? null);
    } catch (err) {
      setFulfillment(null);
      setError(
        err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to load fulfillment.",
      );
    } finally {
      setLoading(false);
    }
  }, [orderId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const handleCreate = async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      const created = await createAdminFulfillment(orderId);
      setFulfillment(created);
    } catch (err) {
      setError(
        err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to create fulfillment.",
      );
    } finally {
      setBusy(false);
    }
  };

  const canCreate =
    !fulfillment &&
    ["paid", "processing", "confirmed", "shipped"].includes(
      String(orderStatus ?? "").toLowerCase(),
    );

  return (
    <section className="admin-card p-4 sm:p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-bold text-zinc-900">Fulfillment</h2>
          <p className="mt-0.5 text-xs text-zinc-500">
            Strategy and status after payment. Shipping is handled later.
          </p>
        </div>
        <Link
          href="/admin/fulfillments"
          className="text-xs font-semibold text-[#8b6914] hover:underline"
        >
          Queue
        </Link>
      </div>

      {loading ? (
        <p className="mt-4 text-sm text-zinc-500">Loading…</p>
      ) : fulfillment ? (
        <dl className="mt-4 space-y-3 text-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-zinc-500">Strategy</dt>
            <dd className="font-semibold text-zinc-900">
              {fulfillment.strategy_label ?? fulfillment.strategy}
            </dd>
          </div>
          <div className="flex items-center justify-between gap-3">
            <dt className="text-zinc-500">Status</dt>
            <dd>
              <span
                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${
                  STATUS_STYLES[fulfillment.status] ??
                  "bg-zinc-50 text-zinc-700 ring-zinc-200"
                }`}
              >
                {fulfillment.status_label ?? fulfillment.status}
              </span>
            </dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-zinc-500">Assigned</dt>
            <dd className="font-medium text-zinc-800">
              {fulfillment.assignee?.name ?? "Unassigned"}
            </dd>
          </div>
          {fulfillment.notes ? (
            <div>
              <dt className="text-zinc-500">Notes</dt>
              <dd className="mt-1 text-zinc-700">{fulfillment.notes}</dd>
            </div>
          ) : null}
        </dl>
      ) : (
        <div className="mt-4">
          <p className="text-sm text-zinc-500">No fulfillment record yet.</p>
          {canCreate ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void handleCreate()}
              className="mt-3 rounded-xl bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-50"
            >
              {busy ? "Creating…" : "Create fulfillment"}
            </button>
          ) : null}
        </div>
      )}

      {error ? (
        <p className="mt-3 text-xs text-red-600" role="alert">
          {error}
        </p>
      ) : null}
    </section>
  );
}
