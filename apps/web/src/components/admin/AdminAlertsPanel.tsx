"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  AdminReportingApiError,
  fetchAdminAlerts,
  type AdminAlert,
  type AdminAlertSource,
  type AdminAlertsPayload,
} from "@/lib/api/admin-reporting";
import {
  adminAlertSeverityBadgeClass,
  adminAlertsEmptyMessage,
  filterAdminAlerts,
  formatAdminAlertSource,
} from "@/lib/admin/admin-alerts";
import { growthSeverityBadgeClass } from "@/lib/admin/growth-intelligence";
import { AdminRefreshStatusBar } from "@/components/admin/AdminRefreshStatusBar";
import { useAdminAutoRefresh } from "@/hooks/use-admin-auto-refresh";

function defaultFrom(): string {
  const d = new Date();
  d.setDate(d.getDate() - 29);
  return d.toISOString().slice(0, 10);
}

function defaultTo(): string {
  return new Date().toISOString().slice(0, 10);
}

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat("en-TZ", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function AlertCard({ alert }: { alert: AdminAlert }) {
  const badgeClass =
    alert.source === "growth"
      ? growthSeverityBadgeClass(alert.severity)
      : adminAlertSeverityBadgeClass(alert.severity);

  const body = (
    <article className="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-sm transition hover:border-zinc-300">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span
              className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${badgeClass}`}
            >
              {alert.severity}
            </span>
            <span className="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
              {formatAdminAlertSource(alert.source)}
            </span>
          </div>
          <h3 className="mt-2 text-sm font-semibold text-zinc-900">{alert.title}</h3>
          <p className="mt-1 text-sm text-zinc-600">{alert.message}</p>
        </div>
        <time className="shrink-0 text-xs text-zinc-500">{formatWhen(alert.created_at)}</time>
      </div>
    </article>
  );

  if (alert.href) {
    return (
      <Link href={alert.href} className="block">
        {body}
      </Link>
    );
  }

  return body;
}

export function AdminAlertsPanel() {
  const [from, setFrom] = useState(defaultFrom);
  const [to, setTo] = useState(defaultTo);
  const [appliedFrom, setAppliedFrom] = useState(defaultFrom);
  const [appliedTo, setAppliedTo] = useState(defaultTo);
  const [data, setData] = useState<AdminAlertsPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sourceFilter, setSourceFilter] = useState<AdminAlertSource | "all">("all");
  const markSyncedRef = useRef<() => void>(() => {});

  const load = useCallback(async (rangeFrom: string, rangeTo: string, options?: { background?: boolean }) => {
    if (!options?.background) {
      setLoading(true);
    }
    setError(null);
    try {
      const next = await fetchAdminAlerts({ from: rangeFrom, to: rangeTo });
      setData(next);
      setAppliedFrom(next.period.from);
      setAppliedTo(next.period.to);
      setFrom(next.period.from);
      setTo(next.period.to);
      markSyncedRef.current();
    } catch (err) {
      if (!options?.background) {
        setData(null);
      }
      setError(err instanceof AdminReportingApiError ? err.message : "Unable to load alerts.");
    } finally {
      if (!options?.background) {
        setLoading(false);
      }
    }
  }, []);

  const refreshAlerts = useCallback(
    async (options?: { background?: boolean }) => {
      await load(appliedFrom, appliedTo, options);
    },
    [appliedFrom, appliedTo, load],
  );

  const autoRefresh = useAdminAutoRefresh({
    page: "alerts",
    enabled: Boolean(data),
    onRefresh: (options) => refreshAlerts(options),
  });
  markSyncedRef.current = autoRefresh.markSynced;

  useEffect(() => {
    void load(appliedFrom, appliedTo);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filteredAlerts = useMemo(
    () => filterAdminAlerts(data?.alerts ?? [], sourceFilter),
    [data?.alerts, sourceFilter],
  );

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="admin-page-header">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">Alert center</p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">Alerts</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Operational exceptions and growth signals in one place.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <AdminRefreshStatusBar
            lastUpdatedAt={autoRefresh.lastUpdatedAt}
            isRefreshing={autoRefresh.isRefreshing || loading}
            policyLabel={autoRefresh.policyLabel}
            onRefresh={() => void autoRefresh.refreshNow({ manual: true })}
            className="w-full sm:w-auto"
          />
          <label className="block min-w-[9rem] flex-1 text-xs font-semibold text-zinc-500 sm:flex-none">
            From
            <input
              type="date"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className="admin-touch-input mt-1 block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
            />
          </label>
          <label className="block min-w-[9rem] flex-1 text-xs font-semibold text-zinc-500 sm:flex-none">
            To
            <input
              type="date"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className="admin-touch-input mt-1 block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
            />
          </label>
          <button type="button" onClick={() => void load(from, to)} className="admin-btn-primary w-full sm:w-auto">
            Apply
          </button>
          <Link href="/admin" className="admin-btn-secondary w-full sm:w-auto">
            Dashboard
          </Link>
        </div>
      </div>

      {error ? (
        <div className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      <div className="mt-6 flex flex-wrap items-center gap-2">
        {(["all", "operational", "growth"] as const).map((filter) => (
          <button
            key={filter}
            type="button"
            onClick={() => setSourceFilter(filter)}
            className={`admin-touch-target rounded-lg px-3 py-2 text-xs font-bold transition ${
              sourceFilter === filter
                ? "bg-zinc-950 text-[#e8c547]"
                : "border border-zinc-200 bg-white text-zinc-600 hover:text-zinc-900"
            }`}
          >
            {filter === "all" ? "All alerts" : formatAdminAlertSource(filter)}
            {data ? (
              <span className="ml-1.5 opacity-70">
                (
                {filter === "all"
                  ? data.counts.total
                  : filter === "operational"
                    ? data.counts.operational
                    : data.counts.growth}
                )
              </span>
            ) : null}
          </button>
        ))}
      </div>

      {loading && !data ? (
        <div className="mt-8 space-y-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="h-24 animate-pulse rounded-xl bg-zinc-100" />
          ))}
        </div>
      ) : (
        <div className={`mt-6 space-y-3 ${loading ? "opacity-60" : ""}`}>
          {filteredAlerts.length === 0 ? (
            <div className="admin-card px-5 py-12 text-center text-sm text-zinc-500">
              {adminAlertsEmptyMessage(sourceFilter)}
            </div>
          ) : (
            filteredAlerts.map((alert, index) => (
              <AlertCard key={`${alert.source}-${alert.title}-${index}`} alert={alert} />
            ))
          )}
        </div>
      )}
    </div>
  );
}
