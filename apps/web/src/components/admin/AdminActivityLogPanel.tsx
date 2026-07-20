"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminActivityLogsApiError,
  fetchAdminActivityLog,
  fetchAdminActivityLogs,
  type AdminActivityLog,
} from "@/lib/api/admin-activity-logs";

const EVENT_OPTIONS = [
  { value: "all", label: "All events" },
  { value: "product_created", label: "Product Created" },
  { value: "product_updated", label: "Product Updated" },
  { value: "shipping_option_updated", label: "Shipping Option Updated" },
  { value: "order_created", label: "Order Created" },
  { value: "payment_confirmed", label: "Payment Confirmed" },
  { value: "warehouse_job_created", label: "Warehouse Job Created" },
  { value: "warehouse_status_changed", label: "Warehouse Status Changed" },
  { value: "shipment_created", label: "Shipment Created" },
  { value: "tracking_event_added", label: "Tracking Event Added" },
  { value: "notification_template_updated", label: "Notification Template Updated" },
  { value: "notification_sent", label: "Notification Sent" },
  { value: "admin_login", label: "Admin Login" },
  { value: "admin_logout", label: "Admin Logout" },
];

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

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

export function AdminActivityLogPanel() {
  const [rows, setRows] = useState<AdminActivityLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [eventType, setEventType] = useState("all");
  const [actorType, setActorType] = useState("all");
  const [search, setSearch] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<AdminActivityLog | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { rows: data } = await fetchAdminActivityLogs({
        eventType: eventType === "all" ? undefined : eventType,
        actorType: actorType === "all" ? undefined : actorType,
        search: search.trim() || undefined,
        dateFrom: dateFrom || undefined,
        dateTo: dateTo || undefined,
      });
      setRows(data);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminActivityLogsApiError
          ? err.message
          : "Unable to load activity logs.",
      );
    } finally {
      setLoading(false);
    }
  }, [eventType, actorType, search, dateFrom, dateTo]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      return;
    }
    let cancelled = false;
    setDetailLoading(true);
    void fetchAdminActivityLog(selectedId)
      .then((row) => {
        if (!cancelled) setDetail(row);
      })
      .catch(() => {
        if (!cancelled) setDetail(null);
      })
      .finally(() => {
        if (!cancelled) setDetailLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [selectedId]);

  const timelineGroups = useMemo(() => {
    const groups = new Map<string, AdminActivityLog[]>();
    for (const row of rows) {
      const day = row.created_at?.slice(0, 10) ?? "Unknown";
      const list = groups.get(day) ?? [];
      list.push(row);
      groups.set(day, list);
    }
    return Array.from(groups.entries());
  }, [rows]);

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-900">Activity Log</h1>
        <p className="mt-1 text-sm text-zinc-600">
          Immutable audit trail of admin and system business actions.
        </p>
      </div>

      <div className="flex flex-wrap gap-3">
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search description…"
          className="min-w-[200px] flex-1 rounded-md border border-zinc-300 px-3 py-2 text-sm"
        />
        <select
          value={eventType}
          onChange={(e) => setEventType(e.target.value)}
          className="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
        >
          {EVENT_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
        <select
          value={actorType}
          onChange={(e) => setActorType(e.target.value)}
          className="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All actors</option>
          <option value="admin">Admin</option>
          <option value="system">System</option>
          <option value="customer">Customer</option>
        </select>
        <input
          type="date"
          value={dateFrom}
          onChange={(e) => setDateFrom(e.target.value)}
          className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
        />
        <input
          type="date"
          value={dateTo}
          onChange={(e) => setDateTo(e.target.value)}
          className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
        />
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-50"
        >
          Refresh
        </button>
      </div>

      {error ? (
        <p className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </p>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div className="space-y-6">
          {loading ? (
            <p className="text-sm text-zinc-500">Loading…</p>
          ) : rows.length === 0 ? (
            <p className="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-600">
              No activity logs match these filters.
            </p>
          ) : (
            timelineGroups.map(([day, items]) => (
              <section key={day}>
                <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  {day}
                </h2>
                <ol className="relative space-y-0 border-l border-zinc-200 pl-4">
                  {items.map((row) => (
                    <li key={row.id} className="relative pb-5">
                      <span className="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-zinc-400 ring-4 ring-white" />
                      <button
                        type="button"
                        onClick={() => setSelectedId(row.id)}
                        className={`w-full rounded-md border px-3 py-3 text-left transition ${
                          selectedId === row.id
                            ? "border-amber-300 bg-amber-50"
                            : "border-zinc-200 bg-white hover:border-zinc-300"
                        }`}
                      >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                          <div>
                            <p className="text-sm font-semibold text-zinc-900">
                              {row.event_type_label ?? row.event_type}
                            </p>
                            <p className="mt-1 text-sm text-zinc-600">{row.description}</p>
                          </div>
                          <span className="text-xs text-zinc-400">{formatWhen(row.created_at)}</span>
                        </div>
                        <p className="mt-2 text-xs text-zinc-500">
                          {row.actor_type}
                          {row.actor?.email ? ` · ${row.actor.email}` : ""}
                          {row.changes && row.changes.length > 0
                            ? ` · ${row.changes.length} change${row.changes.length === 1 ? "" : "s"}`
                            : ""}
                        </p>
                      </button>
                    </li>
                  ))}
                </ol>
              </section>
            ))
          )}
        </div>

        <aside className="h-fit rounded-md border border-zinc-200 bg-white p-4 lg:sticky lg:top-6">
          <h2 className="text-sm font-semibold text-zinc-900">Details</h2>
          {!selectedId ? (
            <p className="mt-3 text-sm text-zinc-500">Select an activity to view details.</p>
          ) : detailLoading ? (
            <p className="mt-3 text-sm text-zinc-500">Loading…</p>
          ) : !detail ? (
            <p className="mt-3 text-sm text-zinc-500">Unable to load details.</p>
          ) : (
            <div className="mt-3 space-y-4 text-sm">
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Event</p>
                <p className="font-medium text-zinc-900">
                  {detail.event_type_label ?? detail.event_type}
                </p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Description</p>
                <p className="text-zinc-700">{detail.description}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Actor</p>
                <p className="text-zinc-700">
                  {detail.actor_type}
                  {detail.actor?.email ? ` · ${detail.actor.email}` : ""}
                </p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">When</p>
                <p className="text-zinc-700">{formatWhen(detail.created_at)}</p>
              </div>
              {detail.ip_address ? (
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-500">IP</p>
                  <p className="text-zinc-700">{detail.ip_address}</p>
                </div>
              ) : null}

              {(detail.changes?.length ?? 0) > 0 ? (
                <div>
                  <p className="mb-2 text-xs uppercase tracking-wide text-zinc-500">
                    Changes
                  </p>
                  <ul className="space-y-2">
                    {detail.changes?.map((change) => (
                      <li
                        key={change.field}
                        className="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2"
                      >
                        <p className="font-medium text-zinc-800">{change.field}</p>
                        <p className="mt-1 text-xs">
                          <span className="rounded bg-red-100 px-1.5 py-0.5 text-red-800 line-through">
                            {formatValue(change.old)}
                          </span>
                          <span className="mx-1 text-zinc-400">→</span>
                          <span className="rounded bg-green-100 px-1.5 py-0.5 text-green-800">
                            {formatValue(change.new)}
                          </span>
                        </p>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}
