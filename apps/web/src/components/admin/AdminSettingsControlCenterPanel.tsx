"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  canViewSettingsControlCenter,
  configurationHealthScoreTone,
  configurationHealthStatusTone,
  formatSettingsTimestamp,
  mapSettingsDashboardPayload,
  type SettingsDashboardView,
} from "@/lib/admin/settings-control-center";
import {
  AdminSettingsDashboardApiError,
  fetchSettingsDashboard,
} from "@/lib/api/admin-settings-dashboard";

export function AdminSettingsControlCenterPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewSettingsControlCenter(permissions);
  const [dashboard, setDashboard] = useState<SettingsDashboardView | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setDashboard(null);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const payload = await fetchSettingsDashboard();
      setDashboard(mapSettingsDashboardPayload(payload, permissions));
    } catch (err) {
      setDashboard(null);
      setError(
        err instanceof AdminSettingsDashboardApiError
          ? err.message
          : "Unable to load settings dashboard.",
      );
    } finally {
      setLoading(false);
    }
  }, [canView, permissions]);

  useEffect(() => {
    if (permissionsLoading) {
      return;
    }
    void load();
  }, [permissionsLoading, load]);

  if (permissionsLoading) {
    return (
      <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-600">
        Checking permissions…
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6">
        <h1 className="text-xl font-semibold text-zinc-900">Settings</h1>
        <p className="mt-2 text-sm text-zinc-600">
          You need <code className="text-zinc-800">settings.view</code> to open the Settings
          Control Center.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900">Settings Control Center</h1>
          <p className="mt-1 text-sm text-zinc-600">
            Read-only overview of configuration health, module status, and recent settings
            changes.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            href="/admin/settings/history"
            className="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-800 hover:bg-zinc-100"
          >
            History
          </Link>
          <button
            type="button"
            onClick={() => void load()}
            className="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-800 hover:bg-zinc-100"
          >
            Refresh
          </button>
        </div>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {loading || !dashboard ? (
        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-600">
          Loading settings dashboard…
        </div>
      ) : (
        <>
          <section className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-600">Health score</p>
              <p
                className={`mt-2 text-4xl font-semibold ${configurationHealthScoreTone(dashboard.healthScore)}`}
              >
                {dashboard.healthScore}
              </p>
              <p className="mt-1 text-sm text-zinc-600">Status: {dashboard.status}</p>
            </div>
            <div className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-600">Critical</p>
              <p className="mt-2 text-4xl font-semibold text-red-700">
                {dashboard.summary.criticalCount}
              </p>
            </div>
            <div className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-600">Warnings</p>
              <p className="mt-2 text-4xl font-semibold text-amber-600">
                {dashboard.summary.warningCount}
              </p>
            </div>
          </section>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-medium text-zinc-900">Modules</h2>
              <Link
                href="/admin/settings/health"
                className="text-sm text-[#c9a227] hover:underline"
              >
                Full health report
              </Link>
            </div>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {dashboard.modules.map((module) => (
                <Link
                  key={module.key}
                  href={module.href}
                  className={`rounded-xl border p-5 transition hover:opacity-95 ${configurationHealthStatusTone(module.status)}`}
                >
                  <div className="flex items-center justify-between gap-3">
                    <h3 className="text-base font-medium">{module.label}</h3>
                    <span className="text-xs uppercase tracking-wide">{module.status}</span>
                  </div>
                  <p className="mt-2 text-sm opacity-90">{module.message}</p>
                </Link>
              ))}
            </div>
          </section>

          {dashboard.quickActions.length > 0 ? (
            <section className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-5">
              <h2 className="text-lg font-medium text-zinc-900">Quick actions</h2>
              <ul className="mt-3 flex flex-wrap gap-2">
                {dashboard.quickActions.map((action) => (
                  <li key={action.key}>
                    <Link
                      href={action.href}
                      className="inline-flex rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-800 hover:bg-zinc-100"
                    >
                      {action.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          <section className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-5">
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-medium text-zinc-900">Recent changes</h2>
              <Link
                href="/admin/settings/history"
                className="text-sm text-[#c9a227] hover:underline"
              >
                View all
              </Link>
            </div>
            {dashboard.recentChanges.length === 0 ? (
              <p className="text-sm text-zinc-600">No configuration changes recorded yet.</p>
            ) : (
              <ul className="divide-y divide-zinc-800">
                {dashboard.recentChanges.map((change) => (
                  <li key={change.id} className="py-3 first:pt-0 last:pb-0">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <p className="text-sm font-medium text-zinc-900">{change.eventLabel}</p>
                      <p className="text-xs text-zinc-600">
                        {formatSettingsTimestamp(change.timestamp)}
                      </p>
                    </div>
                    <p className="mt-1 text-sm text-zinc-600">
                      by {change.actorName}
                      {change.description ? ` — ${change.description}` : ""}
                    </p>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </div>
  );
}
