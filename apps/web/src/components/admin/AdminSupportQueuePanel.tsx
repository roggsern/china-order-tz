"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import { fetchAdminUsers } from "@/lib/api/admin-admins";
import {
  AdminSupportApiError,
  assignAdminSupportTicket,
  canAssignSupport,
  canManageSupport,
  canViewSupport,
  fetchAdminSupportTicket,
  fetchAdminSupportTickets,
  replyAdminSupportTicket,
  updateAdminSupportTicketStatus,
  type SupportTicketRecord,
} from "@/lib/api/admin-support";

const STATUSES = ["all", "new", "open", "in_progress", "waiting_customer", "resolved", "closed", "reopened"];
const CATEGORIES = ["all", "order_issue", "payment_issue", "delivery_issue", "product_issue", "return_issue", "general"];

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

export function AdminSupportQueuePanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewSupport(permissions);
  const canManage = canManageSupport(permissions);
  const canAssign = canAssignSupport(permissions);

  const [rows, setRows] = useState<SupportTicketRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState("all");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<SupportTicketRecord | null>(null);
  const [reply, setReply] = useState("");
  const [assignAdminId, setAssignAdminId] = useState("");
  const [adminOptions, setAdminOptions] = useState<{ id: string; label: string }[]>([]);
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
      const data = await fetchAdminSupportTickets({
        status: statusFilter === "all" ? undefined : statusFilter,
        category: categoryFilter === "all" ? undefined : categoryFilter,
      });
      setRows(data);
    } catch (err) {
      setError(err instanceof AdminSupportApiError ? err.message : "Unable to load tickets.");
    } finally {
      setLoading(false);
    }
  }, [canView, statusFilter, categoryFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!canAssign) return;
    void (async () => {
      try {
        const result = await fetchAdminUsers({ per_page: 100 });
        setAdminOptions(result.data.map((a) => ({ id: a.id, label: `${a.name} (${a.email})` })));
      } catch {
        setAdminOptions([]);
      }
    })();
  }, [canAssign]);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      return;
    }
    let cancelled = false;
    setBusy(true);
    void (async () => {
      try {
        const ticket = await fetchAdminSupportTicket(selectedId);
        if (!cancelled) setDetail(ticket);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof AdminSupportApiError ? err.message : "Unable to load ticket.");
        }
      } finally {
        if (!cancelled) setBusy(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [selectedId]);

  async function handleAssign() {
    if (!detail || !assignAdminId || !canAssign) return;
    setBusy(true);
    try {
      const updated = await assignAdminSupportTicket(detail.id, assignAdminId);
      setDetail(updated);
      setAssignAdminId("");
      await reload();
    } catch (err) {
      setError(err instanceof AdminSupportApiError ? err.message : "Unable to assign.");
    } finally {
      setBusy(false);
    }
  }

  async function handleStatus(status: string) {
    if (!detail || !canManage) return;
    setBusy(true);
    try {
      const updated = await updateAdminSupportTicketStatus(detail.id, status);
      setDetail(updated);
      await reload();
    } catch (err) {
      setError(err instanceof AdminSupportApiError ? err.message : "Unable to update status.");
    } finally {
      setBusy(false);
    }
  }

  async function handleReply(waitingForCustomer: boolean) {
    if (!detail || !reply.trim() || !canManage) return;
    setBusy(true);
    try {
      const updated = await replyAdminSupportTicket(detail.id, reply.trim(), waitingForCustomer);
      setDetail(updated);
      setReply("");
      await reload();
    } catch (err) {
      setError(err instanceof AdminSupportApiError ? err.message : "Unable to send reply.");
    } finally {
      setBusy(false);
    }
  }

  if (!canView) {
    return (
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <p className="text-sm text-zinc-600">
          You need <code className="text-zinc-900">support.view</code> to access the support queue.
        </p>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Support Center"
        description="Customer issues, requests, and conversation history."
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-2">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="admin-input max-w-xs"
        >
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {s === "all" ? "All statuses" : s.replace(/_/g, " ")}
            </option>
          ))}
        </select>
        <select
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className="admin-input max-w-xs"
        >
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {c === "all" ? "All categories" : c.replace(/_/g, " ")}
            </option>
          ))}
        </select>
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <div className="admin-card overflow-hidden">
          {loading ? (
            <p className="p-4 text-sm text-zinc-600">Loading tickets…</p>
          ) : rows.length === 0 ? (
            <AdminEmptyState title="No tickets match your filters" />
          ) : (
            <ul className="divide-y divide-zinc-200">
              {rows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    onClick={() => setSelectedId(row.id)}
                    className={`w-full px-4 py-3 text-left hover:bg-zinc-50 ${
                      selectedId === row.id ? "bg-zinc-50" : ""
                    }`}
                  >
                    <p className="text-sm font-medium text-zinc-900">{row.subject}</p>
                    <p className="mt-1 text-xs text-zinc-600">
                      {row.ticket_number} · {row.status_label} · {row.category_label}
                    </p>
                    <p className="mt-1 text-xs text-zinc-600">
                      {row.customer?.name ?? "Customer"} · {formatWhen(row.updated_at)}
                    </p>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="admin-card p-4">
          {!detail ? (
            <p className="text-sm text-zinc-600">Select a ticket to view the conversation.</p>
          ) : (
            <div className="space-y-4">
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-600">{detail.ticket_number}</p>
                <h2 className="mt-1 text-lg font-semibold text-zinc-900">{detail.subject}</h2>
                <p className="mt-2 text-sm text-zinc-600">
                  {detail.customer?.name} · {detail.customer?.email}
                </p>
                {detail.order ? (
                  <p className="mt-1 text-sm text-zinc-600">
                    Order:{" "}
                    <Link
                      href={`/admin/orders/${detail.order.id}`}
                      className="font-medium text-[#8b6914] hover:underline"
                    >
                      {detail.order.order_number}
                    </Link>
                  </p>
                ) : null}
                <p className="mt-2 text-xs text-zinc-600">
                  {detail.status_label} · {detail.priority_label} · {detail.category_label}
                </p>
              </div>

              {canAssign ? (
                <div className="flex flex-wrap gap-2 border-t border-zinc-200 pt-3">
                  <select
                    value={assignAdminId}
                    onChange={(e) => setAssignAdminId(e.target.value)}
                    className="admin-input min-w-[200px] flex-1"
                  >
                    <option value="">Assign to…</option>
                    {adminOptions.map((opt) => (
                      <option key={opt.id} value={opt.id}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    disabled={busy || !assignAdminId}
                    onClick={() => void handleAssign()}
                    className="admin-btn-primary disabled:opacity-50"
                  >
                    Assign
                  </button>
                </div>
              ) : null}

              {canManage ? (
                <div className="flex flex-wrap gap-2">
                  {["open", "in_progress", "waiting_customer", "resolved", "closed", "reopened"].map(
                    (status) => (
                      <button
                        key={status}
                        type="button"
                        disabled={busy || detail.status === status}
                        onClick={() => void handleStatus(status)}
                        className={`rounded-lg border px-2 py-1 text-xs font-medium disabled:opacity-40 ${
                          detail.status === status
                            ? "border-[#c9a227]/50 bg-[#c9a227]/15 text-[#8b6914]"
                            : "border-zinc-200 text-zinc-700 hover:bg-zinc-50"
                        }`}
                      >
                        {status.replace(/_/g, " ")}
                      </button>
                    ),
                  )}
                </div>
              ) : null}

              <ul className="max-h-72 space-y-3 overflow-y-auto border-t border-zinc-200 pt-3">
                {(detail.messages ?? []).map((msg) => (
                  <li
                    key={msg.id}
                    className={`rounded-lg px-3 py-2 text-sm ${
                      msg.sender_type === "admin"
                        ? "border border-[#c9a227]/30 bg-[#c9a227]/10 text-zinc-900"
                        : "border border-zinc-200 bg-zinc-50 text-zinc-700"
                    }`}
                  >
                    <p className="text-[10px] uppercase tracking-wide text-zinc-600">
                      {msg.sender_type}
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">{msg.message}</p>
                    <p className="mt-1 text-[10px] text-zinc-600">{formatWhen(msg.created_at)}</p>
                  </li>
                ))}
              </ul>

              {canManage ? (
                <div className="space-y-2 border-t border-zinc-200 pt-3">
                  <textarea
                    value={reply}
                    onChange={(e) => setReply(e.target.value)}
                    rows={3}
                    placeholder="Write a reply…"
                    className="admin-input"
                  />
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      disabled={busy || !reply.trim()}
                      onClick={() => void handleReply(false)}
                      className="admin-btn-primary disabled:opacity-50"
                    >
                      Send reply
                    </button>
                    <button
                      type="button"
                      disabled={busy || !reply.trim()}
                      onClick={() => void handleReply(true)}
                      className="admin-btn-secondary disabled:opacity-50"
                    >
                      Reply & wait for customer
                    </button>
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
