"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  formatPermissionDomainLabel,
  groupRolePermissionsByRisk,
  permissionRiskBadgeClass,
  permissionRiskLabel,
  type PermissionRiskTier,
} from "@/lib/admin/admin-permission-catalog";
import {
  AdminRolesApiError,
  canManageRolePermissions,
  canViewAdminRoles,
  fetchAdminRole,
  type AdminRoleDetail,
  type AdminRolePermission,
} from "@/lib/api/admin-roles";
import { AdminRolePermissionEditor } from "@/components/admin/AdminRolePermissionEditor";
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

function PermissionCard({ permission }: { permission: AdminRolePermission }) {
  const tier = permission.risk_tier ?? "medium";

  return (
    <li className="rounded-md border border-zinc-800 bg-zinc-950/40 px-3 py-2">
      <div className="flex items-start justify-between gap-2">
        <p className="font-mono text-xs text-zinc-700">{permission.slug}</p>
        <RiskBadge tier={tier} />
      </div>
      <p className="mt-1 text-sm text-zinc-600">{permission.name}</p>
      {permission.description ? (
        <p className="mt-1 text-xs text-zinc-600">{permission.description}</p>
      ) : null}
    </li>
  );
}

function RiskTierSection({
  tier,
  permissions,
}: {
  tier: PermissionRiskTier;
  permissions: AdminRolePermission[];
}) {
  if (permissions.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2">
      <h4 className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">
        {permissionRiskLabel(tier)} risk
      </h4>
      <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        {permissions.map((permission) => (
          <PermissionCard key={permission.id} permission={permission} />
        ))}
      </ul>
    </div>
  );
}

export function AdminRoleDetailPanel({ roleId }: { roleId: string }) {
  const { permissions } = useAdminPermissions();
  const canView = canViewAdminRoles(permissions);
  const canManagePermissions = canManageRolePermissions(permissions);
  const [detail, setDetail] = useState<AdminRoleDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [editingPermissions, setEditingPermissions] = useState(false);

  const reload = useCallback(async () => {
    if (!canView) {
      setDetail(null);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      setDetail(await fetchAdminRole(roleId));
    } catch (err) {
      setDetail(null);
      setError(err instanceof AdminRolesApiError ? err.message : "Unable to load role detail.");
    } finally {
      setLoading(false);
    }
  }, [canView, roleId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const domainGroups = useMemo(() => {
    if (!detail) {
      return [];
    }

    return detail.permissions_by_domain.map((group) => ({
      ...group,
      byRisk: groupRolePermissionsByRisk(
        group.permissions.map((permission) => ({
          ...permission,
          risk_tier: permission.risk_tier ?? "medium",
        })),
      ),
    }));
  }, [detail]);

  if (!canView) {
    return (
      <div className="space-y-4 p-4 md:p-6">
        <h1 className="text-xl font-semibold text-zinc-900">Role detail</h1>
        <div className="rounded-md border border-amber-900/50 bg-amber-950/30 px-3 py-2 text-sm text-amber-900">
          You do not have permission to view roles.
        </div>
      </div>
    );
  }

  if (loading && !detail) {
    return (
      <div className="p-4 md:p-6 text-sm text-zinc-600">
        Loading role detail…
      </div>
    );
  }

  if (error || !detail) {
    return (
      <div className="space-y-4 p-4 md:p-6">
        <Link href="/admin/settings/roles" className="text-sm text-[#e8c547] hover:underline">
          ← Back to roles
        </Link>
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-800">
          {error ?? "Role not found."}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-4 md:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link href="/admin/settings/roles" className="text-sm text-[#e8c547] hover:underline">
            ← Back to roles
          </Link>
          <h1 className="mt-3 text-xl font-semibold text-zinc-900">{detail.role.name}</h1>
          <p className="mt-1 font-mono text-sm text-zinc-600">{detail.role.slug}</p>
          {detail.role.description ? (
            <p className="mt-2 text-sm text-zinc-600">{detail.role.description}</p>
          ) : null}
        </div>
        {canManagePermissions ? (
          <button
            type="button"
            onClick={() => {
              setSuccess(null);
              setEditingPermissions(true);
            }}
            className="rounded-md border border-[#e8c547]/40 bg-[#e8c547]/10 px-4 py-2 text-sm font-medium text-[#e8c547] hover:bg-[#e8c547]/20"
          >
            Edit permissions
          </button>
        ) : null}
      </div>

      {success ? (
        <div className="rounded-md border border-emerald-900/50 bg-emerald-950/30 px-3 py-2 text-sm text-emerald-800">
          {success}
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">
            Assigned users
          </p>
          <p className="mt-1.5 text-lg font-semibold text-zinc-900">{detail.role.users_count}</p>
        </div>
        <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">
            Permissions
          </p>
          <p className="mt-1.5 text-lg font-semibold text-zinc-900">
            {detail.role.permissions_count}
          </p>
        </div>
      </div>

      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-zinc-900">Assigned admins</h2>
        <div className="overflow-x-auto rounded-lg border border-zinc-800">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-zinc-900 text-[11px] uppercase tracking-wider text-zinc-600">
              <tr>
                <th className="px-3 py-2.5">Name</th>
                <th className="px-3 py-2.5">Email</th>
                <th className="px-3 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody>
              {detail.assigned_admins.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-3 py-6 text-zinc-600">
                    No admins are assigned to this role.
                  </td>
                </tr>
              ) : (
                detail.assigned_admins.map((admin) => (
                  <tr key={admin.id} className="border-t border-zinc-800/80">
                    <td className="px-3 py-2.5 text-zinc-800">
                      {admin.name}
                      {admin.is_super_admin ? (
                        <span className="ml-2 text-xs text-amber-700">Super admin</span>
                      ) : null}
                    </td>
                    <td className="px-3 py-2.5 text-zinc-600">{admin.email}</td>
                    <td className="px-3 py-2.5 text-zinc-700">
                      {admin.is_active ? "Active" : "Inactive"}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="space-y-4">
        <h2 className="text-sm font-semibold text-zinc-900">Permission matrix</h2>
        {domainGroups.length === 0 ? (
          <p className="text-sm text-zinc-600">This role has no permissions assigned.</p>
        ) : (
          domainGroups.map((group) => (
            <div key={group.domain} className="rounded-lg border border-zinc-800 bg-zinc-900/30 p-4">
              <h3 className="text-sm font-semibold text-[#e8c547]">
                {formatPermissionDomainLabel(group.domain)}
                <span className="ml-2 font-mono text-xs font-normal text-zinc-600">
                  {group.domain}.*
                </span>
              </h3>
              <div className="mt-4 space-y-4">
                <RiskTierSection tier="high" permissions={group.byRisk.high} />
                <RiskTierSection tier="medium" permissions={group.byRisk.medium} />
                <RiskTierSection tier="low" permissions={group.byRisk.low} />
              </div>
            </div>
          ))
        )}
      </section>

      {canManagePermissions ? (
        <AdminRolePermissionEditor
          roleId={roleId}
          detail={detail}
          open={editingPermissions}
          onClose={() => setEditingPermissions(false)}
          onSaved={(updated) => {
            setDetail(updated);
            setSuccess("Role permissions updated successfully.");
            setEditingPermissions(false);
          }}
        />
      ) : null}
    </div>
  );
}
