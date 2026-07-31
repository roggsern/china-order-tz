"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AdminRefreshStatusBar } from "@/components/admin/AdminRefreshStatusBar";
import { CatalogHealthIssueCards } from "@/components/admin/CatalogHealthIssueCards";
import { CatalogHealthOverview } from "@/components/admin/CatalogHealthOverview";
import { useAdminAutoRefresh } from "@/hooks/use-admin-auto-refresh";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  canViewCatalogHealth,
  mapCatalogHealthPayload,
  type CatalogHealthReportView,
} from "@/lib/admin/catalog-health";
import {
  AdminCatalogHealthApiError,
  fetchCatalogHealth,
} from "@/lib/api/admin-catalog-health";

export function AdminCatalogHealthPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewCatalogHealth(permissions);
  const [report, setReport] = useState<CatalogHealthReportView | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const markSyncedRef = useRef<() => void>(() => {});

  const load = useCallback(async (options?: { background?: boolean }) => {
    if (!options?.background) {
      setLoading(true);
    }
    setError(null);
    try {
      const payload = await fetchCatalogHealth();
      setReport(mapCatalogHealthPayload(payload));
      markSyncedRef.current();
    } catch (err) {
      if (!options?.background) {
        setReport(null);
      }
      setError(
        err instanceof AdminCatalogHealthApiError
          ? err.message
          : "Unable to load catalog health.",
      );
    } finally {
      if (!options?.background) {
        setLoading(false);
      }
    }
  }, []);

  const refresh = useCallback(
    async (options?: { background?: boolean }) => {
      if (!canView) {
        return;
      }
      await load(options);
    },
    [canView, load],
  );

  const autoRefresh = useAdminAutoRefresh({
    page: "catalog_health",
    enabled: canView && !permissionsLoading,
    onRefresh: refresh,
  });

  useEffect(() => {
    markSyncedRef.current = autoRefresh.markSynced;
  }, [autoRefresh.markSynced]);

  useEffect(() => {
    if (permissionsLoading) {
      return;
    }
    if (!canView) {
      setLoading(false);
      setReport(null);
      setError(null);
      return;
    }
    void load();
  }, [canView, load, permissionsLoading]);

  if (permissionsLoading) {
    return (
      <div className="rounded-xl border border-zinc-200 bg-white px-4 py-8 text-sm text-zinc-500 shadow-sm">
        Checking permissions…
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="rounded-xl border border-zinc-200 bg-white px-4 py-8 text-sm text-zinc-600 shadow-sm">
        You need <span className="font-mono text-xs">catalog.view</span> to open Catalog Health.
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold tracking-tight text-zinc-900">Catalog Health</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Read-only quality signals for commerce readiness, media, inventory, and catalog data.
          </p>
        </div>
        <AdminRefreshStatusBar
          lastUpdatedAt={autoRefresh.lastUpdatedAt}
          isRefreshing={autoRefresh.isRefreshing || loading}
          onRefresh={() => void autoRefresh.refreshNow()}
          policyLabel={autoRefresh.policyLabel}
        />
      </div>

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {loading && !report ? (
        <div className="rounded-xl border border-zinc-200 bg-white px-4 py-10 text-center text-sm text-zinc-500 shadow-sm">
          Loading catalog health…
        </div>
      ) : null}

      {report ? (
        <>
          <CatalogHealthOverview summary={report.summary} />
          <CatalogHealthIssueCards groups={report.groups} isEmpty={report.isEmpty} />
        </>
      ) : null}
    </div>
  );
}
