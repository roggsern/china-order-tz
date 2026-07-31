"use client";

import { useCallback, useEffect, useState } from "react";
import { fetchAdminUsers } from "@/lib/api/admin-admins";
import {
  AdminStoreTeamApiError,
  assignStoreTeamMember,
  canManageStoreTeam,
  canViewStoreTeam,
  fetchStoreTeam,
  removeStoreTeamMember,
  scopeLabel,
  updateStoreTeamMember,
  type StoreOperationalScope,
  type StoreTeamMember,
} from "@/lib/api/admin-store-team";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

const SCOPES: StoreOperationalScope[] = ["store_manager", "store_operator", "store_viewer"];

export function AdminStoreTeamPanel({ storeId }: { storeId: string }) {
  const { permissions } = useAdminPermissions();
  const canView = canViewStoreTeam(permissions);
  const canManage = canManageStoreTeam(permissions);

  const [members, setMembers] = useState<StoreTeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [adminOptions, setAdminOptions] = useState<{ id: string; label: string }[]>([]);
  const [assignAdminId, setAssignAdminId] = useState("");
  const [assignScope, setAssignScope] = useState<StoreOperationalScope>("store_operator");

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const rows = await fetchStoreTeam(storeId);
      setMembers(rows);
    } catch (err) {
      setMembers([]);
      setError(err instanceof AdminStoreTeamApiError ? err.message : "Unable to load team.");
    } finally {
      setLoading(false);
    }
  }, [storeId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!canManage) return;
    void (async () => {
      try {
        const result = await fetchAdminUsers({ per_page: 100 });
        setAdminOptions(
          result.data.map((a) => ({
            id: a.id,
            label: `${a.name} (${a.email})`,
          })),
        );
      } catch {
        setAdminOptions([]);
      }
    })();
  }, [canManage]);

  async function handleAssign(event: React.FormEvent) {
    event.preventDefault();
    if (!canManage || !assignAdminId) return;
    setSaving(true);
    setError(null);
    try {
      await assignStoreTeamMember(storeId, {
        admin_id: assignAdminId,
        operational_scope: assignScope,
      });
      setAssignAdminId("");
      await reload();
    } catch (err) {
      setError(err instanceof AdminStoreTeamApiError ? err.message : "Unable to assign admin.");
    } finally {
      setSaving(false);
    }
  }

  async function handleScopeChange(member: StoreTeamMember, scope: StoreOperationalScope) {
    if (!canManage) return;
    setSaving(true);
    setError(null);
    try {
      await updateStoreTeamMember(storeId, member.admin_id, { operational_scope: scope });
      await reload();
    } catch (err) {
      setError(err instanceof AdminStoreTeamApiError ? err.message : "Unable to update assignment.");
    } finally {
      setSaving(false);
    }
  }

  async function handleRemove(member: StoreTeamMember) {
    if (!canManage) return;
    if (!window.confirm(`Remove ${member.admin?.name ?? "this admin"} from the store team?`)) return;
    setSaving(true);
    setError(null);
    try {
      await removeStoreTeamMember(storeId, member.admin_id);
      await reload();
    } catch (err) {
      setError(err instanceof AdminStoreTeamApiError ? err.message : "Unable to remove assignment.");
    } finally {
      setSaving(false);
    }
  }

  if (!canView && members.length === 0 && !loading && error) {
    return (
      <p className="text-sm text-zinc-500">
        {error}
      </p>
    );
  }

  return (
    <div className="space-y-4 rounded-xl border border-zinc-800 bg-zinc-950/40 p-5">
      {error ? <p className="text-sm text-red-400">{error}</p> : null}

      {loading ? (
        <p className="text-sm text-zinc-400">Loading team…</p>
      ) : members.length === 0 ? (
        <p className="text-sm text-zinc-400">No admins assigned to this store yet.</p>
      ) : (
        <ul className="divide-y divide-zinc-800">
          {members.map((member) => (
            <li key={member.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div>
                <p className="text-sm font-medium text-zinc-100">
                  {member.admin?.name ?? member.admin_id}
                </p>
                <p className="text-xs text-zinc-500">
                  {member.admin?.email ?? "—"}
                  {member.admin?.role?.name ? ` · ${member.admin.role.name}` : ""}
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {canManage ? (
                  <select
                    value={member.operational_scope}
                    disabled={saving}
                    onChange={(e) =>
                      void handleScopeChange(member, e.target.value as StoreOperationalScope)
                    }
                    className="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-xs text-zinc-200"
                  >
                    {SCOPES.map((scope) => (
                      <option key={scope} value={scope}>
                        {scopeLabel(scope)}
                      </option>
                    ))}
                  </select>
                ) : (
                  <span className="rounded bg-zinc-800 px-2 py-1 text-xs text-zinc-300">
                    {member.operational_scope_label}
                  </span>
                )}
                <span
                  className={`rounded px-2 py-1 text-xs ${
                    member.is_currently_active
                      ? "bg-emerald-900/40 text-emerald-300"
                      : "bg-zinc-800 text-zinc-400"
                  }`}
                >
                  {member.is_currently_active ? "Active" : "Inactive"}
                </span>
                {canManage ? (
                  <button
                    type="button"
                    disabled={saving}
                    onClick={() => void handleRemove(member)}
                    className="rounded border border-red-900/50 px-2 py-1 text-xs text-red-300 hover:bg-red-950/30"
                  >
                    Remove
                  </button>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}

      {canManage ? (
        <form onSubmit={(e) => void handleAssign(e)} className="border-t border-zinc-800 pt-4">
          <p className="text-xs font-semibold uppercase tracking-wide text-zinc-500">Assign admin</p>
          <div className="mt-3 flex flex-wrap gap-2">
            <select
              value={assignAdminId}
              onChange={(e) => setAssignAdminId(e.target.value)}
              className="min-w-[220px] flex-1 rounded border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-200"
            >
              <option value="">Select admin…</option>
              {adminOptions.map((opt) => (
                <option key={opt.id} value={opt.id}>
                  {opt.label}
                </option>
              ))}
            </select>
            <select
              value={assignScope}
              onChange={(e) => setAssignScope(e.target.value as StoreOperationalScope)}
              className="rounded border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-200"
            >
              {SCOPES.map((scope) => (
                <option key={scope} value={scope}>
                  {scopeLabel(scope)}
                </option>
              ))}
            </select>
            <button
              type="submit"
              disabled={saving || !assignAdminId}
              className="rounded-lg border border-[#c9a227]/50 bg-[#c9a227]/10 px-4 py-2 text-sm text-[#c9a227] disabled:opacity-40"
            >
              {saving ? "Saving…" : "Assign"}
            </button>
          </div>
        </form>
      ) : null}
    </div>
  );
}
