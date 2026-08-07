"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  canViewPermissionCatalog,
  filterPermissionCatalog,
  formatPermissionDomainLabel,
  groupPermissionCatalogByDomain,
  listPermissionDomains,
  permissionRiskBadgeClass,
  permissionRiskLabel,
  type AdminPermissionCatalogEntry,
  type PermissionRiskTier,
} from "@/lib/admin/admin-permission-catalog";
import {
  AdminPermissionsApiError,
  fetchAdminPermissionCatalog,
} from "@/lib/api/admin-permissions";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

function RiskBadge({ tier }: { tier: PermissionRiskTier }) {
  return (
    <span
      className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide ${permissionRiskBadgeClass(tier)}`}
    >
      {permissionRiskLabel(tier)}
    </span>
  );
}

function PermissionTable({ rows }: { rows: AdminPermissionCatalogEntry[] }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-zinc-800">
      <table className="min-w-full text-left text-sm">
        <thead className="bg-zinc-900 text-[11px] uppercase tracking-wider text-zinc-500">
          <tr>
            <th className="px-3 py-2.5">Permission slug</th>
            <th className="px-3 py-2.5">Domain</th>
            <th className="px-3 py-2.5">Risk level</th>
            <th className="px-3 py-2.5">Description</th>
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={4} className="px-3 py-6 text-zinc-600">
                No permissions match these filters.
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={row.id} className="border-t border-zinc-800/80 hover:bg-zinc-50">
                <td className="px-3 py-2.5 font-mono text-xs text-zinc-800">{row.slug}</td>
                <td className="px-3 py-2.5 text-zinc-700">{row.domain}</td>
                <td className="px-3 py-2.5">
                  <RiskBadge tier={row.risk_tier} />
                </td>
                <td className="px-3 py-2.5 text-zinc-700">{row.description ?? row.name}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

export function AdminPermissionsPanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewPermissionCatalog(permissions);
  const [rows, setRows] = useState<AdminPermissionCatalogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [domain, setDomain] = useState("");
  const [risk, setRisk] = useState<PermissionRiskTier | "">("");
  const [groupByDomain, setGroupByDomain] = useState(true);

  const reload = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const catalog = await fetchAdminPermissionCatalog();
      setRows(catalog.permissions);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminPermissionsApiError ? err.message : "Unable to load permission catalog.",
      );
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const filteredRows = useMemo(
    () => filterPermissionCatalog(rows, { search, domain, risk }),
    [rows, search, domain, risk],
  );

  const domainOptions = useMemo(() => listPermissionDomains(rows), [rows]);
  const groupedRows = useMemo(
    () => groupPermissionCatalogByDomain(filteredRows),
    [filteredRows],
  );

  if (!canView) {
    return (
      <div className="space-y-4 p-4 md:p-6">
        <h1 className="text-xl font-semibold text-zinc-900">Permissions</h1>
        <div className="rounded-md border border-amber-900/50 bg-amber-950/30 px-3 py-2 text-sm text-amber-900">
          You do not have permission to view the permission catalog.
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-4 md:p-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-900">Permissions</h1>
        <p className="mt-1 text-sm text-zinc-600">
          Read-only catalog of platform permissions with domain and risk classification.
        </p>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <div className="flex flex-wrap items-end gap-2">
        <label className="text-xs text-zinc-600">
          Search
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Slug, domain, description"
            className="mt-1 block w-64 rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          />
        </label>
        <label className="text-xs text-zinc-600">
          Domain
          <select
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          >
            <option value="">All domains</option>
            {domainOptions.map((option) => (
              <option key={option} value={option}>
                {formatPermissionDomainLabel(option)}
              </option>
            ))}
          </select>
        </label>
        <label className="text-xs text-zinc-600">
          Risk
          <select
            value={risk}
            onChange={(e) => setRisk(e.target.value as PermissionRiskTier | "")}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          >
            <option value="">All risk levels</option>
            <option value="low">LOW</option>
            <option value="medium">MEDIUM</option>
            <option value="high">HIGH</option>
          </select>
        </label>
        <label className="flex items-center gap-2 pb-2 text-xs text-zinc-600">
          <input
            type="checkbox"
            checked={groupByDomain}
            onChange={(e) => setGroupByDomain(e.target.checked)}
          />
          Group by domain
        </label>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-700"
        >
          Refresh
        </button>
      </div>

      {loading && rows.length === 0 ? (
        <p className="text-sm text-zinc-600">Loading permission catalog…</p>
      ) : groupByDomain ? (
        <div className="space-y-6">
          {groupedRows.length === 0 ? (
            <p className="text-sm text-zinc-600">No permissions match these filters.</p>
          ) : (
            groupedRows.map((group) => (
              <section key={group.domain} className="space-y-3">
                <h2 className="text-sm font-semibold text-[#c9a227]">
                  {formatPermissionDomainLabel(group.domain)}
                  <span className="ml-2 font-mono text-xs font-normal text-zinc-600">
                    {group.domain}.*
                  </span>
                </h2>
                <PermissionTable rows={group.permissions} />
              </section>
            ))
          )}
        </div>
      ) : (
        <PermissionTable rows={filteredRows} />
      )}
    </div>
  );
}
