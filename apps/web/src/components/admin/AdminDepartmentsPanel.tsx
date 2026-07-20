"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminDepartment,
  deleteAdminDepartment,
  fetchAdminDepartments,
  restoreAdminDepartment,
  updateAdminDepartment,
  type AdminDepartment,
} from "@/lib/api/admin-catalog";

type DepartmentFormState = {
  id?: string;
  name: string;
  icon: string;
  image: string;
  description: string;
  sortOrder: number;
  isActive: boolean;
};

const emptyForm = (): DepartmentFormState => ({
  name: "",
  icon: "",
  image: "",
  description: "",
  sortOrder: 0,
  isActive: true,
});

const PAGE_SIZE = 15;

export function AdminDepartmentsPanel() {
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [trashed, setTrashed] = useState<AdminDepartment[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [page, setPage] = useState(1);
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<DepartmentFormState | null>(null);
  const [saving, setSaving] = useState(false);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [nextDepartments, deleted] = await Promise.all([
        fetchAdminDepartments(),
        fetchAdminDepartments({ trashed: true }),
      ]);
      setDepartments(nextDepartments);
      setTrashed(deleted);
    } catch (err) {
      setDepartments([]);
      setTrashed([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load departments from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const filteredDepartments = useMemo(() => {
    const q = search.trim().toLowerCase();
    return departments.filter((department) => {
      if (statusFilter === "active" && !department.isActive) return false;
      if (statusFilter === "inactive" && department.isActive) return false;
      if (!q) return true;
      return (
        department.name.toLowerCase().includes(q) ||
        department.slug.toLowerCase().includes(q) ||
        department.description.toLowerCase().includes(q)
      );
    });
  }, [departments, search, statusFilter]);

  const totalPages = Math.max(1, Math.ceil(filteredDepartments.length / PAGE_SIZE));
  const currentPage = Math.min(page, totalPages);
  const pagedDepartments = filteredDepartments.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE,
  );

  useEffect(() => {
    setPage(1);
  }, [search, statusFilter]);

  const openCreate = () => {
    setActionError(null);
    setForm({
      ...emptyForm(),
      sortOrder: departments.length > 0
        ? Math.max(...departments.map((item) => item.sortOrder)) + 1
        : 1,
    });
  };

  const openEdit = (department: AdminDepartment) => {
    setActionError(null);
    setForm({
      id: department.id,
      name: department.name,
      icon: department.icon === "🏬" ? "" : department.icon,
      image: department.image ?? "",
      description: department.description,
      sortOrder: department.sortOrder,
      isActive: department.isActive,
    });
  };

  const handleDelete = async (department: AdminDepartment) => {
    if (
      !window.confirm(
        `Delete department “${department.name}”? You can restore it later.`,
      )
    ) {
      return;
    }
    setActionError(null);
    try {
      await deleteAdminDepartment(department.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to delete department.",
      );
    }
  };

  const handleRestore = async (department: AdminDepartment) => {
    setActionError(null);
    try {
      await restoreAdminDepartment(department.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to restore department.",
      );
    }
  };

  const handleToggleActive = async (department: AdminDepartment) => {
    setActionError(null);
    try {
      await updateAdminDepartment(department.id, {
        name: department.name,
        icon: department.icon === "🏬" ? null : department.icon,
        image: department.image,
        description: department.description || null,
        sort_order: department.sortOrder,
        is_active: !department.isActive,
      });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to update department status.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Department name is required.");
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = {
      name: form.name.trim(),
      icon: form.icon.trim() || null,
      image: form.image.trim() || null,
      description: form.description.trim() || null,
      sort_order: Number.isFinite(form.sortOrder) ? form.sortOrder : 0,
      is_active: form.isActive,
    };

    try {
      if (form.id) {
        await updateAdminDepartment(form.id, payload);
      } else {
        await createAdminDepartment(payload);
      }
      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save department.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Departments</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Top-level catalog departments for storefront browsing and admin organization.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="admin-btn-secondary"
            onClick={() => setShowTrashed((value) => !value)}
          >
            {showTrashed ? "Hide trash" : `Trash (${trashed.length})`}
          </button>
          <button type="button" className="admin-btn-primary" onClick={openCreate}>
            Add department
          </button>
        </div>
      </div>

      {actionError ? (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {actionError}
        </div>
      ) : null}

      {form ? (
        <div className="admin-card mb-4 p-5">
          <h2 className="text-sm font-semibold text-zinc-900">
            {form.id ? "Edit department" : "New department"}
          </h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="department-name">
                Name *
              </label>
              <input
                id="department-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="department-description">
                Description
              </label>
              <textarea
                id="department-description"
                className="admin-input mt-1.5 min-h-[80px]"
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="department-icon">
                Icon (emoji or key)
              </label>
              <input
                id="department-icon"
                className="admin-input mt-1.5"
                placeholder="📱"
                value={form.icon}
                onChange={(event) => setForm({ ...form, icon: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="department-image">
                Image URL
              </label>
              <input
                id="department-image"
                className="admin-input mt-1.5"
                placeholder="https://… or storage path"
                value={form.image}
                onChange={(event) => setForm({ ...form, image: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="department-sort-order">
                Sort order
              </label>
              <input
                id="department-sort-order"
                type="number"
                min={0}
                className="admin-input mt-1.5"
                value={form.sortOrder}
                onChange={(event) =>
                  setForm({
                    ...form,
                    sortOrder: Number.parseInt(event.target.value, 10) || 0,
                  })
                }
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-zinc-700">
              <input
                type="checkbox"
                checked={form.isActive}
                onChange={(event) => setForm({ ...form, isActive: event.target.checked })}
              />
              Active
            </label>
          </div>

          <div className="mt-4 flex gap-2">
            <button
              type="button"
              className="admin-btn-primary"
              disabled={saving}
              onClick={() => void handleSave()}
            >
              {saving ? "Saving…" : "Save"}
            </button>
            <button
              type="button"
              className="admin-btn-secondary"
              disabled={saving}
              onClick={() => setForm(null)}
            >
              Cancel
            </button>
          </div>
        </div>
      ) : null}

      <div className="admin-card overflow-hidden">
        <div className="flex flex-wrap gap-3 border-b border-zinc-200 px-4 py-3">
          <input
            type="search"
            className="admin-input min-w-[200px] flex-1"
            placeholder="Search departments…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input w-auto"
            value={statusFilter}
            onChange={(event) =>
              setStatusFilter(event.target.value as "all" | "active" | "inactive")
            }
          >
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        {isLoading ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            Loading departments…
          </div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load departments</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : filteredDepartments.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No departments configured.
          </div>
        ) : (
          <>
            <ul className="divide-y divide-zinc-100">
              {pagedDepartments.map((department) => (
                <li key={department.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                  <span className="text-2xl">{department.icon}</span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {department.name}
                      {!department.isActive ? (
                        <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                          Inactive
                        </span>
                      ) : null}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {department.slug} · sort {department.sortOrder}
                    </p>
                    {department.description ? (
                      <p className="mt-1 text-xs text-zinc-600">{department.description}</p>
                    ) : null}
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => void handleToggleActive(department)}
                    >
                      {department.isActive ? "Deactivate" : "Activate"}
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(department)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                      onClick={() => void handleDelete(department)}
                    >
                      Delete
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            {totalPages > 1 ? (
              <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                <p className="text-xs text-zinc-500">
                  Page {currentPage} of {totalPages} · {filteredDepartments.length} total
                </p>
                <div className="flex gap-2">
                  <button
                    type="button"
                    className="admin-btn-secondary"
                    disabled={currentPage <= 1}
                    onClick={() => setPage((value) => Math.max(1, value - 1))}
                  >
                    Previous
                  </button>
                  <button
                    type="button"
                    className="admin-btn-secondary"
                    disabled={currentPage >= totalPages}
                    onClick={() => setPage((value) => Math.min(totalPages, value + 1))}
                  >
                    Next
                  </button>
                </div>
              </div>
            ) : null}
          </>
        )}
      </div>

      {showTrashed ? (
        <div className="admin-card mt-4 overflow-hidden">
          <div className="border-b border-zinc-200 px-5 py-3">
            <h2 className="text-sm font-semibold text-zinc-900">Deleted departments</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((department) => (
                <li key={department.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{department.name}</p>
                    <p className="text-xs text-zinc-500">{department.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(department)}
                  >
                    Restore
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
}
