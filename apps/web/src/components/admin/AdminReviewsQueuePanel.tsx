"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminReviewsApiError,
  approveAdminReview,
  canManageAdminReviews,
  canViewAdminReviews,
  fetchAdminReview,
  fetchAdminReviews,
  rejectAdminReview,
  reviewStatusBadgeClass,
  reviewStatusLabel,
  type AdminReviewRecord,
} from "@/lib/api/admin-reviews";

const STATUSES = ["pending", "all", "approved", "rejected"];

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(
      new Date(value),
    );
  } catch {
    return value;
  }
}

function renderStars(rating: number): string {
  return "★".repeat(Math.max(0, Math.min(5, rating))) + "☆".repeat(Math.max(0, 5 - rating));
}

export function AdminReviewsQueuePanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewAdminReviews(permissions);
  const canManage = canManageAdminReviews(permissions);

  const [rows, setRows] = useState<AdminReviewRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState("pending");
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<AdminReviewRecord | null>(null);
  const [moderationNote, setModerationNote] = useState("");
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminReviews({
        status: statusFilter,
        search: search.trim() || undefined,
      });
      setRows(data);
    } catch (err) {
      setError(err instanceof AdminReviewsApiError ? err.message : "Unable to load reviews.");
    } finally {
      setLoading(false);
    }
  }, [canView, statusFilter, search]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      setModerationNote("");
      return;
    }
    let cancelled = false;
    setBusy(true);
    void (async () => {
      try {
        const review = await fetchAdminReview(selectedId);
        if (!cancelled) {
          setDetail(review);
          setModerationNote(review.moderation_note ?? "");
        }
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof AdminReviewsApiError ? err.message : "Unable to load review.");
        }
      } finally {
        if (!cancelled) setBusy(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [selectedId]);

  async function handleApprove() {
    if (!detail || !canManage || detail.status !== "pending") return;
    setBusy(true);
    try {
      const updated = await approveAdminReview(detail.id, moderationNote.trim() || undefined);
      setDetail(updated);
      setSelectedId(updated.id);
      await reload();
    } catch (err) {
      setError(err instanceof AdminReviewsApiError ? err.message : "Unable to approve review.");
    } finally {
      setBusy(false);
    }
  }

  async function handleReject() {
    if (!detail || !canManage || detail.status !== "pending") return;
    setBusy(true);
    try {
      const updated = await rejectAdminReview(detail.id, moderationNote.trim() || undefined);
      setDetail(updated);
      setSelectedId(updated.id);
      await reload();
    } catch (err) {
      setError(err instanceof AdminReviewsApiError ? err.message : "Unable to reject review.");
    } finally {
      setBusy(false);
    }
  }

  if (!canView) {
    return (
      <p className="text-sm text-zinc-500">
        You need <code className="text-zinc-700">reviews.view</code> to access the review queue.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <div className="flex flex-wrap gap-2">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-900"
        >
          {STATUSES.map((status) => (
            <option key={status} value={status}>
              {status === "all" ? "All statuses" : reviewStatusLabel(status)}
            </option>
          ))}
        </select>
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search product, customer, or comment"
          className="min-w-[240px] flex-1 rounded border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-900"
        />
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50"
        >
          Refresh
        </button>
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <section className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            {loading ? "Loading reviews…" : `${rows.length} review${rows.length === 1 ? "" : "s"}`}
          </div>
          <ul className="max-h-[70vh] divide-y divide-zinc-100 overflow-y-auto">
            {rows.map((row) => (
              <li key={row.id}>
                <button
                  type="button"
                  onClick={() => setSelectedId(row.id)}
                  className={`w-full px-4 py-3 text-left hover:bg-zinc-50 ${
                    selectedId === row.id ? "bg-zinc-50" : ""
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium text-zinc-900">
                        {row.product?.name ?? "Unknown product"}
                      </p>
                      <p className="mt-1 text-xs text-amber-600">{renderStars(row.rating)}</p>
                      <p className="mt-1 line-clamp-2 text-xs text-zinc-500">
                        {row.comment ?? row.title ?? "No comment"}
                      </p>
                    </div>
                    <span
                      className={`inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ${reviewStatusBadgeClass(row.status)}`}
                    >
                      {row.status_label ?? reviewStatusLabel(row.status)}
                    </span>
                  </div>
                  <p className="mt-2 text-[11px] text-zinc-400">
                    {row.customer?.name ?? "Customer"} · {formatWhen(row.created_at)}
                  </p>
                </button>
              </li>
            ))}
            {!loading && rows.length === 0 ? (
              <li className="px-4 py-8 text-center text-sm text-zinc-500">No reviews found.</li>
            ) : null}
          </ul>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white">
          {!detail ? (
            <div className="px-4 py-12 text-center text-sm text-zinc-500">
              Select a review to inspect and moderate.
            </div>
          ) : (
            <div className="space-y-4 p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold text-zinc-900">
                    {detail.product?.name ?? "Review details"}
                  </h2>
                  <p className="mt-1 text-sm text-amber-600">{renderStars(detail.rating)}</p>
                </div>
                <span
                  className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${reviewStatusBadgeClass(detail.status)}`}
                >
                  {detail.status_label ?? reviewStatusLabel(detail.status)}
                </span>
              </div>

              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-zinc-500">Customer</dt>
                  <dd className="font-medium text-zinc-900">{detail.customer?.name ?? "—"}</dd>
                  <dd className="text-xs text-zinc-500">{detail.customer?.email ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Submitted</dt>
                  <dd className="font-medium text-zinc-900">{formatWhen(detail.created_at)}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Product</dt>
                  <dd className="font-medium text-zinc-900">
                    {detail.product ? (
                      <Link href={`/admin/products/${detail.product.id}`} className="text-sky-700 hover:underline">
                        {detail.product.name}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Order</dt>
                  <dd className="font-medium text-zinc-900">
                    {detail.order ? (
                      <Link href={`/admin/orders/${detail.order.id}`} className="text-sky-700 hover:underline">
                        {detail.order.order_number}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </dd>
                </div>
              </dl>

              {detail.title ? (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Title</p>
                  <p className="mt-1 text-sm text-zinc-900">{detail.title}</p>
                </div>
              ) : null}

              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Comment</p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-zinc-900">
                  {detail.comment ?? "No comment provided."}
                </p>
              </div>

              {canManage && detail.status === "pending" ? (
                <div className="space-y-3 border-t border-zinc-200 pt-4">
                  <label className="block text-sm text-zinc-700">
                    Moderation note (optional)
                    <textarea
                      value={moderationNote}
                      onChange={(e) => setModerationNote(e.target.value)}
                      rows={3}
                      className="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm text-zinc-900"
                      placeholder="Internal note or rejection reason for the customer"
                    />
                  </label>
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void handleApprove()}
                      className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                    >
                      Approve
                    </button>
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void handleReject()}
                      className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
                    >
                      Reject
                    </button>
                  </div>
                </div>
              ) : detail.moderation_note ? (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Moderation note</p>
                  <p className="mt-1 text-sm text-zinc-700">{detail.moderation_note}</p>
                </div>
              ) : null}

              {detail.moderated_by ? (
                <p className="text-xs text-zinc-500">
                  Moderated by {detail.moderated_by.name} · {formatWhen(detail.moderated_at)}
                </p>
              ) : null}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
