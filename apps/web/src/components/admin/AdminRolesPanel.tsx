"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminRolesApiError,
  canViewAdminRoles,
  fetchAdminRoles,
  sortRoleSummaries,
  type AdminRoleMatrixSummary,
} from "@/lib/api/admin-roles";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

export function AdminRolesPanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewAdminRoles(permissions);
  const [rows, setRows] = useState<AdminRoleMatrixSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");

  const reload = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      setRows(sortRoleSummaries(await fetchAdminRoles()));
    } catch (err) {
      setRows([]);
      setError(err instanceof AdminRolesApiError ? err.message : "Unable to load roles.");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const filteredRows = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return rows;
    return rows.filter(
      (row) =>
        row.name.toLowerCase().includes(term) ||
        row.slug.toLowerCase().includes(term) ||
        (row.description ?? "").toLowerCase().includes(term),
    );
  }, [rows, search]);

  if (!canView) {
    return (
      <div className="space-y-4 p-4 md:p-6">
        <h1 className="text-xl font-semibold text-zinc-900">Roles</h1>
        <div className="rounded-md border border-amber-900/50 bg-amber-950/30 px-3 py-2 text-sm text-amber-900">
          You do not have permission to view roles.
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-4 md:p-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-900">Roles</h1>
        <p className="mt-1 text-sm text-zinc-600">
          Read-only view of platform roles and their permission matrix.
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
            placeholder="Role name or slug"
            className="mt-1 block w-64 rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          />
        </label>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-700"
        >
          Refresh
        </button>
      </div>

      <div className="overflow-x-auto rounded-lg border border-zinc-800">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-zinc-900 text-[11px] uppercase tracking-wider text-zinc-500">
            <tr>
              <th className="px-3 py-2.5">Role</th>
              <th className="px-3 py-2.5">Slug</th>
              <th className="px-3 py-2.5">Users</th>
              <th className="px-3 py-2.5">Permissions</th>
              <th className="px-3 py-2.5">Description</th>
            </tr>
          </thead>
          <tbody>
            {loading && filteredRows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-3 py-6 text-zinc-600">
                  Loading…
                </td>
              </tr>
            ) : filteredRows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-3 py-6 text-zinc-600">
                  No roles match this search.
                </td>
              </tr>
            ) : (
              filteredRows.map((row) => (
                <tr key={row.id} className="border-t border-zinc-800/80 hover:bg-zinc-50">
                  <td className="px-3 py-2.5">
                    <Link
                      href={`/admin/settings/roles/${row.id}`}
                      className="font-medium text-[#c9a227] hover:underline"
                    >
                      {row.name}
                    </Link>
                  </td>
                  <td className="px-3 py-2.5 font-mono text-xs text-zinc-700">{row.slug}</td>
                  <td className="px-3 py-2.5 text-zinc-800">{row.users_count}</td>
                  <td className="px-3 py-2.5 text-zinc-800">{row.permissions_count}</td>
                  <td className="px-3 py-2.5 text-zinc-700">{row.description ?? "—"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
