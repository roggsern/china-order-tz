"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  canViewSettingsControlCenter,
  formatSettingsAuditJson,
  formatSettingsTimestamp,
  mapSettingsAuditChange,
  type SettingsAuditChangeView,
} from "@/lib/admin/settings-control-center";
import {
  AdminSettingsDashboardApiError,
  fetchSettingsHistory,
} from "@/lib/api/admin-settings-dashboard";

export function AdminSettingsHistoryPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewSettingsControlCenter(permissions);
  const [rows, setRows] = useState<SettingsAuditChangeView[]>([]);
  const [events, setEvents] = useState<string[]>([]);
  const [eventFilter, setEventFilter] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setRows([]);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const payload = await fetchSettingsHistory({
        event: eventFilter || undefined,
        page,
        perPage: 20,
      });
      setRows(payload.data.map(mapSettingsAuditChange));
      setEvents(payload.filters.events);
      setLastPage(payload.meta.last_page);
      setTotal(payload.meta.total);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminSettingsDashboardApiError
          ? err.message
          : "Unable to load settings history.",
      );
    } finally {
      setLoading(false);
    }
  }, [canView, eventFilter, page]);

  useEffect(() => {
    if (permissionsLoading) {
      return;
    }
    void load();
  }, [permissionsLoading, load]);

  if (permissionsLoading) {
    return (
      <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-400">
        Checking permissions…
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6">
        <h1 className="text-xl font-semibold text-zinc-100">Settings history</h1>
        <p className="mt-2 text-sm text-zinc-400">
          You need <code className="text-zinc-300">settings.view</code> to view configuration
          audit history.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-100">Settings history</h1>
          <p className="mt-1 text-sm text-zinc-400">
            Read-only audit trail for configuration changes. Secrets are redacted.
          </p>
        </div>
        <Link
          href="/admin/settings"
          className="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200 hover:bg-zinc-900"
        >
          Back to overview
        </Link>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <label className="block text-sm text-zinc-300">
          Event
          <select
            value={eventFilter}
            onChange={(event) => {
              setPage(1);
              setEventFilter(event.target.value);
            }}
            className="mt-1 block min-w-[16rem] rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
          >
            <option value="">All configuration events</option>
            {events.map((event) => (
              <option key={event} value={event}>
                {event}
              </option>
            ))}
          </select>
        </label>
        <button
          type="button"
          onClick={() => void load()}
          className="rounded-lg border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900"
        >
          Refresh
        </button>
        <p className="pb-2 text-xs text-zinc-500">{total} change{total === 1 ? "" : "s"}</p>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-400">
          Loading settings history…
        </div>
      ) : rows.length === 0 ? (
        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-400">
          No matching configuration changes found.
        </div>
      ) : (
        <div className="space-y-3">
          {rows.map((row) => (
            <article
              key={row.id}
              className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-5"
            >
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-base font-medium text-zinc-100">{row.eventLabel}</h2>
                <p className="text-xs text-zinc-500">{formatSettingsTimestamp(row.timestamp)}</p>
              </div>
              <p className="mt-1 text-sm text-zinc-400">
                Actor: {row.actorName}
                {row.description ? ` — ${row.description}` : ""}
              </p>
              <div className="mt-4 grid gap-3 lg:grid-cols-2">
                <div>
                  <p className="mb-1 text-xs uppercase tracking-wide text-zinc-500">Before</p>
                  <pre className="overflow-x-auto rounded-lg border border-zinc-800 bg-black/30 p-3 text-xs text-zinc-300">
                    {formatSettingsAuditJson(row.before)}
                  </pre>
                </div>
                <div>
                  <p className="mb-1 text-xs uppercase tracking-wide text-zinc-500">After</p>
                  <pre className="overflow-x-auto rounded-lg border border-zinc-800 bg-black/30 p-3 text-xs text-zinc-300">
                    {formatSettingsAuditJson(row.after)}
                  </pre>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}

      {lastPage > 1 ? (
        <div className="flex items-center gap-3">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            className="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200 disabled:opacity-40"
          >
            Previous
          </button>
          <p className="text-sm text-zinc-400">
            Page {page} of {lastPage}
          </p>
          <button
            type="button"
            disabled={page >= lastPage}
            onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
            className="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200 disabled:opacity-40"
          >
            Next
          </button>
        </div>
      ) : null}
    </div>
  );
}
