"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
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
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div className="admin-card px-4 py-8 text-sm text-zinc-600">Checking permissions…</div>
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <AdminPageHeader title="Catalog Health" eyebrow="Catalog" />
        <div className="admin-card px-4 py-8 text-sm text-zinc-600">
          You need <span className="font-mono text-xs text-zinc-900">catalog.view</span> to open
          Catalog Health.
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        eyebrow="Catalog"
        title="Catalog Health"
        description="Read-only quality signals for commerce readiness, media, inventory, and catalog data."
        actions={
          <AdminRefreshStatusBar
            lastUpdatedAt={autoRefresh.lastUpdatedAt}
            isRefreshing={autoRefresh.isRefreshing || loading}
            onRefresh={() => void autoRefresh.refreshNow()}
            policyLabel={autoRefresh.policyLabel}
          />
        }
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {loading && !report ? (
        <div className="admin-card px-4 py-10 text-center text-sm text-zinc-600">
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
