"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import {
  AdminAdminsApiError,
  activateAdminUser,
  assignAdminUserRole,
  createAdminUser,
  deactivateAdminUser,
  fetchAdminAssignableRoles,
  fetchAdminUsers,
  resolveAdminUserActions,
  updateAdminUser,
  type AdminRoleSummary,
  type AdminUserRecord,
} from "@/lib/api/admin-admins";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import { useAdminAuth } from "@/components/admin/AdminAuthProvider";
import { setAdminLoginNotice } from "@/lib/admin/login-notice";

type AdminFormState = {
  name: string;
  email: string;
  phone: string;
  password: string;
  role_id: string;
  is_active: boolean;
};

const emptyForm = (): AdminFormState => ({
  name: "",
  email: "",
  phone: "",
  password: "",
  role_id: "",
  is_active: true,
});

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span
      className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
        active
          ? "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200"
          : "bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200"
      }`}
    >
      {active ? "Active" : "Inactive"}
    </span>
  );
}

export function AdminUsersPanel() {
  const { permissions, adminId } = useAdminPermissions();
  const { signOut } = useAdminAuth();
  const globalActions = useMemo(() => resolveAdminUserActions(permissions), [permissions]);

  const [rows, setRows] = useState<AdminUserRecord[]>([]);
  const [roles, setRoles] = useState<AdminRoleSummary[]>([]);
  const [meta, setMeta] = useState<{ current_page?: number; last_page?: number; total?: number }>(
    {},
  );
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<"" | "active" | "inactive">("");
  const [roleFilter, setRoleFilter] = useState("");
  const [page, setPage] = useState(1);
  const [mode, setMode] = useState<"create" | "edit" | null>(null);
  const [editing, setEditing] = useState<AdminUserRecord | null>(null);
  const [form, setForm] = useState<AdminFormState>(emptyForm);

  const reload = useCallback(async () => {
    if (!globalActions.canView) {
      setRows([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const [list, roleList] = await Promise.all([
        fetchAdminUsers({
          search: search || undefined,
          is_active:
            statusFilter === "active" ? true : statusFilter === "inactive" ? false : undefined,
          role_id: roleFilter || undefined,
          page,
          per_page: 20,
        }),
        fetchAdminAssignableRoles(),
      ]);
      setRows(list.data);
      setMeta(list.meta ?? {});
      setRoles(roleList);
    } catch (err) {
      setRows([]);
      setError(err instanceof AdminAdminsApiError ? err.message : "Unable to load admin users.");
    } finally {
      setLoading(false);
    }
  }, [globalActions.canView, page, roleFilter, search, statusFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const openCreate = () => {
    setMode("create");
    setEditing(null);
    setForm({
      ...emptyForm(),
      role_id: roles[0]?.id ?? "",
    });
  };

  const openEdit = (admin: AdminUserRecord) => {
    setMode("edit");
    setEditing(admin);
    setForm({
      name: admin.name,
      email: admin.email,
      phone: admin.phone ?? "",
      password: "",
      role_id: admin.role?.id ?? "",
      is_active: admin.is_active,
    });
  };

  const closeForm = () => {
    setMode(null);
    setEditing(null);
    setForm(emptyForm());
  };

  const onSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (mode === "create") {
        await createAdminUser({
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim() || null,
          password: form.password,
          role_id: form.role_id,
          is_active: form.is_active,
        });
      } else if (mode === "edit" && editing) {
        const emailChanged =
          form.email.trim().toLowerCase() !== editing.email.trim().toLowerCase();
        const passwordChanged = Boolean(form.password.trim());
        const isSelf = editing.id === adminId;

        await updateAdminUser(editing.id, {
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim() || null,
          ...(passwordChanged ? { password: form.password } : {}),
        });

        if (
          form.role_id &&
          form.role_id !== editing.role?.id &&
          resolveAdminUserActions(permissions, {
            targetIsSuperAdmin: editing.is_super_admin,
            isSelf,
          }).canAssignRole
        ) {
          await assignAdminUserRole(editing.id, form.role_id);
        }

        if (isSelf && (emailChanged || passwordChanged)) {
          setAdminLoginNotice(
            "Your account details have been updated. Please sign in again.",
          );
          closeForm();
          await signOut();
          return;
        }
      }

      closeForm();
      await reload();
    } catch (err) {
      setError(err instanceof AdminAdminsApiError ? err.message : "Unable to save admin user.");
    } finally {
      setBusy(false);
    }
  };

  const onToggleActive = async (admin: AdminUserRecord) => {
    const rowActions = resolveAdminUserActions(permissions, {
      targetIsSuperAdmin: admin.is_super_admin,
      isSelf: admin.id === adminId,
    });

    if (admin.is_active && !rowActions.canDeactivate) return;
    if (!admin.is_active && !rowActions.canActivate) return;

    setBusy(true);
    setError(null);
    try {
      if (admin.is_active) {
        await deactivateAdminUser(admin.id);
      } else {
        await activateAdminUser(admin.id);
      }
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminAdminsApiError ? err.message : "Unable to update admin status.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (!globalActions.canView) {
    return (
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <AdminPageHeader title="Admin Users" />
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          You do not have permission to view admin users.
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Admin Users"
        description="Manage admin accounts, roles, and activation status."
        actions={
          globalActions.canCreate ? (
            <button type="button" onClick={openCreate} className="admin-btn-primary">
              Create admin
            </button>
          ) : null
        }
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {mode ? (
        <section className="admin-card p-5">
          <h2 className="text-sm font-semibold text-zinc-900">
            {mode === "create" ? "Create admin" : "Edit admin"}
          </h2>
          <form onSubmit={(e) => void onSubmit(e)} className="mt-4 grid gap-3 sm:grid-cols-2">
            <input
              className="admin-input"
              placeholder="Name *"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              required
            />
            <input
              className="admin-input"
              placeholder="Email *"
              type="email"
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              required
            />
            <input
              className="admin-input"
              placeholder="Phone"
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
            />
            <input
              className="admin-input"
              placeholder={mode === "create" ? "Password *" : "New password (optional)"}
              type="password"
              value={form.password}
              onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
              required={mode === "create"}
            />
            <select
              className="admin-input"
              value={form.role_id}
              onChange={(e) => setForm((f) => ({ ...f, role_id: e.target.value }))}
              required={mode === "create"}
              disabled={
                mode === "edit" &&
                editing !== null &&
                !resolveAdminUserActions(permissions, {
                  targetIsSuperAdmin: editing.is_super_admin,
                  isSelf: editing.id === adminId,
                }).canAssignRole
              }
            >
              <option value="">Select role</option>
              {roles.map((role) => (
                <option key={role.id} value={role.id}>
                  {role.name}
                </option>
              ))}
            </select>
            {mode === "create" ? (
              <label className="flex items-center gap-2 text-sm text-zinc-600">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                />
                Active on create
              </label>
            ) : null}
            <div className="flex gap-2 sm:col-span-2">
              <button type="submit" disabled={busy} className="admin-btn-primary disabled:opacity-50">
                {busy ? "Saving…" : mode === "create" ? "Create admin" : "Save changes"}
              </button>
              <button type="button" onClick={closeForm} className="admin-btn-secondary">
                Cancel
              </button>
            </div>
          </form>
        </section>
      ) : null}

      <div className="flex flex-wrap items-end gap-2">
        <label className="admin-label">
          Search
          <input
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
            placeholder="Name or email"
            className="admin-input mt-1 w-64"
          />
        </label>
        <label className="admin-label">
          Status
          <select
            value={statusFilter}
            onChange={(e) => {
              setPage(1);
              setStatusFilter(e.target.value as "" | "active" | "inactive");
            }}
            className="admin-input mt-1"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <label className="admin-label">
          Role
          <select
            value={roleFilter}
            onChange={(e) => {
              setPage(1);
              setRoleFilter(e.target.value);
            }}
            className="admin-input mt-1"
          >
            <option value="">All roles</option>
            {roles.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name}
              </option>
            ))}
          </select>
        </label>
        <button type="button" onClick={() => void reload()} className="admin-btn-secondary">
          Refresh
        </button>
      </div>

      <div className="admin-card overflow-hidden">
        <div className="admin-table-scroll">
          <table className="admin-table min-w-full">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading && rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="text-zinc-600">
                    Loading…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="!p-0">
                    <AdminEmptyState title="No admin users match these filters." />
                  </td>
                </tr>
              ) : (
                rows.map((row) => {
                  const rowActions = resolveAdminUserActions(permissions, {
                    targetIsSuperAdmin: row.is_super_admin,
                    isSelf: row.id === adminId,
                  });

                  return (
                    <tr key={row.id}>
                      <td className="admin-table-primary">
                        {row.name}
                        {row.is_super_admin ? (
                          <span className="ml-2 text-xs font-medium text-amber-700">
                            Super admin
                          </span>
                        ) : null}
                      </td>
                      <td className="text-zinc-600">{row.email}</td>
                      <td className="text-zinc-900">{row.role?.name ?? "—"}</td>
                      <td>
                        <StatusBadge active={row.is_active} />
                      </td>
                      <td className="text-zinc-600">
                        {row.created_at ? new Date(row.created_at).toLocaleDateString() : "—"}
                      </td>
                      <td>
                        <div className="flex flex-wrap gap-2">
                          {rowActions.canUpdate ? (
                            <button
                              type="button"
                              onClick={() => openEdit(row)}
                              className="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50"
                            >
                              Edit
                            </button>
                          ) : null}
                          {row.is_active && rowActions.canDeactivate ? (
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => void onToggleActive(row)}
                              className="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-800 disabled:opacity-50"
                            >
                              Deactivate
                            </button>
                          ) : null}
                          {!row.is_active && rowActions.canActivate ? (
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => void onToggleActive(row)}
                              className="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 disabled:opacity-50"
                            >
                              Activate
                            </button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="flex items-center gap-3 text-sm text-zinc-600">
        <button
          type="button"
          disabled={(meta.current_page ?? 1) <= 1}
          onClick={() => setPage((p) => Math.max(1, p - 1))}
          className="admin-btn-secondary disabled:opacity-40"
        >
          Prev
        </button>
        <span>
          Page {meta.current_page ?? 1} / {meta.last_page ?? 1} ({meta.total ?? 0} total)
        </span>
        <button
          type="button"
          disabled={(meta.current_page ?? 1) >= (meta.last_page ?? 1)}
          onClick={() => setPage((p) => p + 1)}
          className="admin-btn-secondary disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  );
}
