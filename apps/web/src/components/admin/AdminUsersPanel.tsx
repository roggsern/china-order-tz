"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
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
        active ? "bg-emerald-950/60 text-emerald-300" : "bg-zinc-800 text-zinc-400"
      }`}
    >
      {active ? "Active" : "Inactive"}
    </span>
  );
}

export function AdminUsersPanel() {
  const { permissions, adminId } = useAdminPermissions();
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
        await updateAdminUser(editing.id, {
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim() || null,
          ...(form.password.trim() ? { password: form.password } : {}),
        });

        if (
          form.role_id &&
          form.role_id !== editing.role?.id &&
          resolveAdminUserActions(permissions, {
            targetIsSuperAdmin: editing.is_super_admin,
            isSelf: editing.id === adminId,
          }).canAssignRole
        ) {
          await assignAdminUserRole(editing.id, form.role_id);
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
      <div className="space-y-4 p-4 md:p-6">
        <h1 className="text-xl font-semibold text-zinc-50">Admin Users</h1>
        <div className="rounded-md border border-amber-900/50 bg-amber-950/30 px-3 py-2 text-sm text-amber-100">
          You do not have permission to view admin users.
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-4 md:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-zinc-50">Admin Users</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Manage admin accounts, roles, and activation status.
          </p>
        </div>
        {globalActions.canCreate ? (
          <button
            type="button"
            onClick={openCreate}
            className="rounded-md bg-[#c9a227] px-3 py-2 text-sm font-semibold text-zinc-950"
          >
            Create admin
          </button>
        ) : null}
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      {mode ? (
        <section className="rounded-lg border border-zinc-800 bg-zinc-900/40 p-5">
          <h2 className="text-sm font-semibold text-zinc-100">
            {mode === "create" ? "Create admin" : "Edit admin"}
          </h2>
          <form onSubmit={(e) => void onSubmit(e)} className="mt-4 grid gap-3 sm:grid-cols-2">
            <input
              className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
              placeholder="Name *"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              required
            />
            <input
              className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
              placeholder="Email *"
              type="email"
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              required
            />
            <input
              className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
              placeholder="Phone"
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
            />
            <input
              className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
              placeholder={mode === "create" ? "Password *" : "New password (optional)"}
              type="password"
              value={form.password}
              onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
              required={mode === "create"}
            />
            <select
              className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
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
              <label className="flex items-center gap-2 text-sm text-zinc-300">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                />
                Active on create
              </label>
            ) : null}
            <div className="flex gap-2 sm:col-span-2">
              <button
                type="submit"
                disabled={busy}
                className="rounded-md bg-[#c9a227] px-3 py-2 text-sm font-semibold text-zinc-950 disabled:opacity-50"
              >
                {busy ? "Saving…" : mode === "create" ? "Create admin" : "Save changes"}
              </button>
              <button
                type="button"
                onClick={closeForm}
                className="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-300"
              >
                Cancel
              </button>
            </div>
          </form>
        </section>
      ) : null}

      <div className="flex flex-wrap items-end gap-2">
        <label className="text-xs text-zinc-500">
          Search
          <input
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
            placeholder="Name or email"
            className="mt-1 block w-64 rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          />
        </label>
        <label className="text-xs text-zinc-500">
          Status
          <select
            value={statusFilter}
            onChange={(e) => {
              setPage(1);
              setStatusFilter(e.target.value as "" | "active" | "inactive");
            }}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <label className="text-xs text-zinc-500">
          Role
          <select
            value={roleFilter}
            onChange={(e) => {
              setPage(1);
              setRoleFilter(e.target.value);
            }}
            className="mt-1 block rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
          >
            <option value="">All roles</option>
            {roles.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name}
              </option>
            ))}
          </select>
        </label>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-300"
        >
          Refresh
        </button>
      </div>

      <div className="overflow-x-auto rounded-lg border border-zinc-800">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-zinc-900 text-[11px] uppercase tracking-wider text-zinc-500">
            <tr>
              <th className="px-3 py-2.5">Name</th>
              <th className="px-3 py-2.5">Email</th>
              <th className="px-3 py-2.5">Role</th>
              <th className="px-3 py-2.5">Status</th>
              <th className="px-3 py-2.5">Created</th>
              <th className="px-3 py-2.5">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading && rows.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-3 py-6 text-zinc-500">
                  Loading…
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-3 py-6 text-zinc-500">
                  No admin users match these filters.
                </td>
              </tr>
            ) : (
              rows.map((row) => {
                const rowActions = resolveAdminUserActions(permissions, {
                  targetIsSuperAdmin: row.is_super_admin,
                  isSelf: row.id === adminId,
                });

                return (
                  <tr key={row.id} className="border-t border-zinc-800/80 hover:bg-zinc-900/40">
                    <td className="px-3 py-2.5 text-zinc-200">
                      {row.name}
                      {row.is_super_admin ? (
                        <span className="ml-2 text-xs text-amber-400">Super admin</span>
                      ) : null}
                    </td>
                    <td className="px-3 py-2.5 text-zinc-400">{row.email}</td>
                    <td className="px-3 py-2.5 text-zinc-300">{row.role?.name ?? "—"}</td>
                    <td className="px-3 py-2.5">
                      <StatusBadge active={row.is_active} />
                    </td>
                    <td className="px-3 py-2.5 text-zinc-400">
                      {row.created_at ? new Date(row.created_at).toLocaleDateString() : "—"}
                    </td>
                    <td className="px-3 py-2.5">
                      <div className="flex flex-wrap gap-2">
                        {rowActions.canUpdate ? (
                          <button
                            type="button"
                            onClick={() => openEdit(row)}
                            className="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300"
                          >
                            Edit
                          </button>
                        ) : null}
                        {row.is_active && rowActions.canDeactivate ? (
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => void onToggleActive(row)}
                            className="rounded border border-red-900/60 px-2 py-1 text-xs text-red-300"
                          >
                            Deactivate
                          </button>
                        ) : null}
                        {!row.is_active && rowActions.canActivate ? (
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => void onToggleActive(row)}
                            className="rounded border border-emerald-900/60 px-2 py-1 text-xs text-emerald-300"
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

      <div className="flex items-center gap-3 text-sm text-zinc-400">
        <button
          type="button"
          disabled={(meta.current_page ?? 1) <= 1}
          onClick={() => setPage((p) => Math.max(1, p - 1))}
          className="rounded border border-zinc-700 px-2 py-1 disabled:opacity-40"
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
          className="rounded border border-zinc-700 px-2 py-1 disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  );
}
