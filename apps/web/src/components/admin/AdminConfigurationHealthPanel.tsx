"use client";

import { useCallback, useEffect, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  configurationHealthScoreTone,
  configurationHealthStatusTone,
  canViewConfigurationHealth,
  mapConfigurationHealthPayload,
  type ConfigurationHealthReportView,
} from "@/lib/admin/configuration-health";
import {
  AdminConfigurationHealthApiError,
  fetchConfigurationHealth,
} from "@/lib/api/admin-configuration-health";

export function AdminConfigurationHealthPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewConfigurationHealth(permissions);
  const [report, setReport] = useState<ConfigurationHealthReportView | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setReport(null);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const payload = await fetchConfigurationHealth();
      setReport(mapConfigurationHealthPayload(payload));
    } catch (err) {
      setReport(null);
      setError(
        err instanceof AdminConfigurationHealthApiError
          ? err.message
          : "Unable to load configuration health.",
      );
    } finally {
      setLoading(false);
    }
  }, [canView]);

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
        <h1 className="text-xl font-semibold text-zinc-100">Configuration health</h1>
        <p className="mt-2 text-sm text-zinc-400">
          You need <code className="text-zinc-300">settings.view</code> to open this page.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-100">Configuration health</h1>
          <p className="mt-1 text-sm text-zinc-400">
            Read-only readiness across payments, shipping, notifications, stores, features, and
            security. Secrets are never exposed.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void load()}
          className="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200 hover:bg-zinc-900"
        >
          Refresh
        </button>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      {loading || !report ? (
        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-400">
          Loading configuration health…
        </div>
      ) : (
        <>
          <section className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-500">Overall score</p>
              <p className={`mt-2 text-4xl font-semibold ${configurationHealthScoreTone(report.overallScore)}`}>
                {report.overallScore}
              </p>
              <p className="mt-1 text-sm text-zinc-400">Status: {report.status}</p>
            </div>
            <div className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-500">Critical issues</p>
              <p className="mt-2 text-4xl font-semibold text-red-300">
                {report.summary.criticalCount}
              </p>
            </div>
            <div className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-500">Warnings</p>
              <p className="mt-2 text-4xl font-semibold text-amber-300">
                {report.summary.warningCount}
              </p>
            </div>
          </section>

          {report.criticalIssues.length > 0 ? (
            <section className="rounded-xl border border-red-900/50 bg-red-950/20 p-5">
              <h2 className="text-lg font-medium text-red-200">Critical issues</h2>
              <ul className="mt-3 space-y-2 text-sm text-red-100/90">
                {report.criticalIssues.map((issue) => (
                  <li key={`${issue.group}-${issue.message}`}>
                    <span className="font-medium uppercase tracking-wide text-red-300">
                      {issue.group}
                    </span>
                    {" — "}
                    {issue.message}
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          {report.warnings.length > 0 ? (
            <section className="rounded-xl border border-amber-900/50 bg-amber-950/20 p-5">
              <h2 className="text-lg font-medium text-amber-200">Warnings</h2>
              <ul className="mt-3 space-y-2 text-sm text-amber-100/90">
                {report.warnings.map((issue) => (
                  <li key={`${issue.group}-${issue.message}`}>
                    <span className="font-medium uppercase tracking-wide text-amber-300">
                      {issue.group}
                    </span>
                    {" — "}
                    {issue.message}
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          <section className="grid gap-3 lg:grid-cols-2">
            {report.groups.map((group) => (
              <article
                key={group.group}
                className={`rounded-xl border p-5 ${configurationHealthStatusTone(group.status)}`}
              >
                <div className="flex items-center justify-between gap-3">
                  <h2 className="text-lg font-medium">{group.title}</h2>
                  <span className="text-xs uppercase tracking-wide">{group.status}</span>
                </div>
                <ul className="mt-3 space-y-2 text-sm opacity-90">
                  {group.checks.map((check) => (
                    <li key={`${check.group}-${check.message}`}>{check.message}</li>
                  ))}
                </ul>
              </article>
            ))}
          </section>
        </>
      )}
    </div>
  );
}
