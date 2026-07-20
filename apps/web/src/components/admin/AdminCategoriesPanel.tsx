"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminCategory,
  deleteAdminCategory,
  fetchAdminCategories,
  fetchAdminDepartments,
  restoreAdminCategory,
  updateAdminCategory,
  type AdminCategory,
  type AdminDepartment,
} from "@/lib/api/admin-catalog";

type CategoryFormState = {
  id?: string;
  departmentId: string;
  name: string;
  slug: string;
  image: string;
  description: string;
  sortOrder: number;
  isActive: boolean;
};

const emptyForm = (departmentId = ""): CategoryFormState => ({
  departmentId,
  name: "",
  slug: "",
  image: "",
  description: "",
  sortOrder: 0,
  isActive: true,
});

const PAGE_SIZE = 15;

export function AdminCategoriesPanel() {
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [trashed, setTrashed] = useState<AdminCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [departmentFilter, setDepartmentFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [page, setPage] = useState(1);
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<CategoryFormState | null>(null);
  const [saving, setSaving] = useState(false);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [nextCategories, deleted, nextDepartments] = await Promise.all([
        fetchAdminCategories({ rootsOnly: true }),
        fetchAdminCategories({ rootsOnly: true, trashed: true }),
        fetchAdminDepartments(),
      ]);
      setCategories(nextCategories.filter((item) => !item.parentId));
      setTrashed(deleted.filter((item) => !item.parentId));
      setDepartments(nextDepartments);
    } catch (err) {
      setCategories([]);
      setTrashed([]);
      setDepartments([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load categories from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const filteredCategories = useMemo(() => {
    const q = search.trim().toLowerCase();
    return categories
      .filter((category) => {
        if (departmentFilter !== "all" && category.departmentId !== departmentFilter) {
          return false;
        }
        if (statusFilter === "active" && !category.isActive) return false;
        if (statusFilter === "inactive" && category.isActive) return false;
        if (!q) return true;
        return (
          category.name.toLowerCase().includes(q) ||
          category.slug.toLowerCase().includes(q) ||
          (category.description ?? "").toLowerCase().includes(q) ||
          (category.departmentName ?? "").toLowerCase().includes(q)
        );
      })
      .sort((a, b) => {
        const deptCmp = (a.departmentName ?? "").localeCompare(b.departmentName ?? "");
        if (deptCmp !== 0) return deptCmp;
        const sortCmp = (a.sortOrder ?? 0) - (b.sortOrder ?? 0);
        if (sortCmp !== 0) return sortCmp;
        return a.name.localeCompare(b.name);
      });
  }, [categories, search, departmentFilter, statusFilter]);

  const totalPages = Math.max(1, Math.ceil(filteredCategories.length / PAGE_SIZE));
  const currentPage = Math.min(page, totalPages);
  const pagedCategories = filteredCategories.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE,
  );

  useEffect(() => {
    setPage(1);
  }, [search, departmentFilter, statusFilter]);

  const openCreate = () => {
    setActionError(null);
    const defaultDepartmentId =
      departmentFilter !== "all"
        ? departmentFilter
        : departments[0]?.id ?? "";
    setForm(emptyForm(defaultDepartmentId));
  };

  const openEdit = (category: AdminCategory) => {
    setActionError(null);
    setForm({
      id: category.id,
      departmentId: category.departmentId ?? "",
      name: category.name,
      slug: category.slug,
      image: category.image ?? "",
      description: category.description ?? "",
      sortOrder: category.sortOrder ?? 0,
      isActive: category.isActive,
    });
  };

  const handleDelete = async (category: AdminCategory) => {
    if (!window.confirm(`Delete category “${category.name}”? You can restore it later.`)) {
      return;
    }
    setActionError(null);
    try {
      await deleteAdminCategory(category.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to delete category.",
      );
    }
  };

  const handleRestore = async (category: AdminCategory) => {
    setActionError(null);
    try {
      await restoreAdminCategory(category.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to restore category.",
      );
    }
  };

  const handleToggleActive = async (category: AdminCategory) => {
    if (!category.departmentId) {
      setActionError("Assign a department before changing status.");
      return;
    }
    setActionError(null);
    try {
      await updateAdminCategory(category.id, {
        name: category.name,
        department_id: category.departmentId,
        slug: category.slug,
        image: category.image ?? null,
        description: category.description || null,
        sort_order: category.sortOrder ?? 0,
        is_active: !category.isActive,
      });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to update category status.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Category name is required.");
      return;
    }
    if (!form.departmentId) {
      setActionError("Department is required.");
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = {
      name: form.name.trim(),
      department_id: form.departmentId,
      slug: form.slug.trim() || null,
      image: form.image.trim() || null,
      description: form.description.trim() || null,
      sort_order: Number.isFinite(form.sortOrder) ? form.sortOrder : 0,
      is_active: form.isActive,
    };

    try {
      if (form.id) {
        await updateAdminCategory(form.id, payload);
      } else {
        await createAdminCategory(payload);
      }
      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to save category.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Categories</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Categories belong to departments. Filter, search, and manage catalog groups.
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
            Add category
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
            {form.id ? "Edit category" : "New category"}
          </h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label className="admin-label" htmlFor="category-department">
                Department *
              </label>
              <select
                id="category-department"
                className="admin-input mt-1.5"
                value={form.departmentId}
                onChange={(event) => setForm({ ...form, departmentId: event.target.value })}
              >
                <option value="">Select department</option>
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>
                    {department.icon} {department.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="category-sort-order">
                Sort order
              </label>
              <input
                id="category-sort-order"
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
            <div>
              <label className="admin-label" htmlFor="category-name">
                Name *
              </label>
              <input
                id="category-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="category-slug">
                Slug
              </label>
              <input
                id="category-slug"
                className="admin-input mt-1.5"
                placeholder="auto from name if empty"
                value={form.slug}
                onChange={(event) => setForm({ ...form, slug: event.target.value })}
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="category-image">
                Image URL
              </label>
              <input
                id="category-image"
                className="admin-input mt-1.5"
                placeholder="https://… or storage path"
                value={form.image}
                onChange={(event) => setForm({ ...form, image: event.target.value })}
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="category-description">
                Description
              </label>
              <textarea
                id="category-description"
                className="admin-input mt-1.5 min-h-[80px]"
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
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
            placeholder="Search categories…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input w-auto"
            value={departmentFilter}
            onChange={(event) => setDepartmentFilter(event.target.value)}
          >
            <option value="all">All departments</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
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
          <div className="px-5 py-12 text-center text-sm text-zinc-500">Loading categories…</div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load categories</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : filteredCategories.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No categories configured.
          </div>
        ) : (
          <>
            <ul className="divide-y divide-zinc-100">
              {pagedCategories.map((category) => (
                <li key={category.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {category.name}
                      {!category.isActive ? (
                        <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                          Inactive
                        </span>
                      ) : null}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {category.departmentIcon ? `${category.departmentIcon} ` : ""}
                      {category.departmentName ?? "No department"} · {category.slug} · sort{" "}
                      {category.sortOrder ?? 0}
                      {` · ${category.productsCount} products`}
                    </p>
                    {category.description ? (
                      <p className="mt-1 text-xs text-zinc-600">{category.description}</p>
                    ) : null}
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => void handleToggleActive(category)}
                    >
                      {category.isActive ? "Deactivate" : "Activate"}
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(category)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                      onClick={() => void handleDelete(category)}
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
                  Page {currentPage} of {totalPages} · {filteredCategories.length} total
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
            <h2 className="text-sm font-semibold text-zinc-900">Deleted categories</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((category) => (
                <li key={category.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{category.name}</p>
                    <p className="text-xs text-zinc-500">{category.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(category)}
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
