"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminSubcategory,
  deleteAdminSubcategory,
  fetchAdminCategories,
  fetchAdminDepartments,
  fetchAdminSubcategories,
  restoreAdminSubcategory,
  updateAdminSubcategory,
  type AdminCategory,
  type AdminDepartment,
  type AdminSubcategory,
} from "@/lib/api/admin-catalog";

type SubcategoryFormState = {
  id?: string;
  departmentId: string;
  categoryId: string;
  name: string;
  slug: string;
  image: string;
  description: string;
  sortOrder: number;
  isActive: boolean;
};

const emptyForm = (departmentId = "", categoryId = ""): SubcategoryFormState => ({
  departmentId,
  categoryId,
  name: "",
  slug: "",
  image: "",
  description: "",
  sortOrder: 0,
  isActive: true,
});

const PAGE_SIZE = 15;

export function AdminSubcategoriesPanel() {
  const [subcategories, setSubcategories] = useState<AdminSubcategory[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [trashed, setTrashed] = useState<AdminSubcategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [departmentFilter, setDepartmentFilter] = useState("all");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [page, setPage] = useState(1);
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<SubcategoryFormState | null>(null);
  const [saving, setSaving] = useState(false);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [nextSubcategories, deleted, nextCategories, nextDepartments] = await Promise.all([
        fetchAdminSubcategories(),
        fetchAdminSubcategories({ trashed: true }),
        fetchAdminCategories({ rootsOnly: true }),
        fetchAdminDepartments(),
      ]);
      setSubcategories(nextSubcategories);
      setTrashed(deleted);
      setCategories(nextCategories.filter((item) => !item.parentId));
      setDepartments(nextDepartments);
    } catch (err) {
      setSubcategories([]);
      setTrashed([]);
      setCategories([]);
      setDepartments([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load subcategories from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const rootCategories = useMemo(
    () =>
      categories
        .filter((category) => !category.parentId && category.departmentId)
        .sort((a, b) => {
          const deptCmp = (a.departmentName ?? "").localeCompare(b.departmentName ?? "");
          if (deptCmp !== 0) return deptCmp;
          return a.name.localeCompare(b.name);
        }),
    [categories],
  );

  const categoriesForFilter = useMemo(() => {
    if (departmentFilter === "all") return rootCategories;
    return rootCategories.filter((category) => category.departmentId === departmentFilter);
  }, [rootCategories, departmentFilter]);

  const categoriesForForm = useMemo(() => {
    if (!form?.departmentId) return [];
    return rootCategories.filter((category) => category.departmentId === form.departmentId);
  }, [rootCategories, form?.departmentId]);

  const filteredSubcategories = useMemo(() => {
    const q = search.trim().toLowerCase();
    return subcategories
      .filter((item) => {
        if (departmentFilter !== "all" && item.departmentId !== departmentFilter) return false;
        if (categoryFilter !== "all" && item.categoryId !== categoryFilter) return false;
        if (statusFilter === "active" && !item.isActive) return false;
        if (statusFilter === "inactive" && item.isActive) return false;
        if (!q) return true;
        return (
          item.name.toLowerCase().includes(q) ||
          item.slug.toLowerCase().includes(q) ||
          item.description.toLowerCase().includes(q) ||
          item.categoryName.toLowerCase().includes(q) ||
          (item.departmentName ?? "").toLowerCase().includes(q)
        );
      })
      .sort((a, b) => {
        const deptCmp = (a.departmentName ?? "").localeCompare(b.departmentName ?? "");
        if (deptCmp !== 0) return deptCmp;
        const catCmp = a.categoryName.localeCompare(b.categoryName);
        if (catCmp !== 0) return catCmp;
        const sortCmp = a.sortOrder - b.sortOrder;
        if (sortCmp !== 0) return sortCmp;
        return a.name.localeCompare(b.name);
      });
  }, [subcategories, search, departmentFilter, categoryFilter, statusFilter]);

  const totalPages = Math.max(1, Math.ceil(filteredSubcategories.length / PAGE_SIZE));
  const currentPage = Math.min(page, totalPages);
  const pagedSubcategories = filteredSubcategories.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE,
  );

  useEffect(() => {
    setPage(1);
  }, [search, departmentFilter, categoryFilter, statusFilter]);

  useEffect(() => {
    if (categoryFilter === "all") return;
    const stillValid = categoriesForFilter.some((category) => category.id === categoryFilter);
    if (!stillValid) setCategoryFilter("all");
  }, [categoriesForFilter, categoryFilter]);

  const openCreate = () => {
    setActionError(null);
    const defaultDepartmentId =
      departmentFilter !== "all" ? departmentFilter : departments[0]?.id ?? "";
    const defaultCategoryId =
      categoryFilter !== "all"
        ? categoryFilter
        : rootCategories.find((category) => category.departmentId === defaultDepartmentId)?.id ??
          "";
    setForm(emptyForm(defaultDepartmentId, defaultCategoryId));
  };

  const openEdit = (subcategory: AdminSubcategory) => {
    setActionError(null);
    setForm({
      id: subcategory.id,
      departmentId: subcategory.departmentId ?? "",
      categoryId: subcategory.categoryId,
      name: subcategory.name,
      slug: subcategory.slug,
      image: subcategory.image ?? "",
      description: subcategory.description,
      sortOrder: subcategory.sortOrder,
      isActive: subcategory.isActive,
    });
  };

  const handleDelete = async (subcategory: AdminSubcategory) => {
    if (
      !window.confirm(
        `Delete subcategory “${subcategory.name}”? You can restore it later.`,
      )
    ) {
      return;
    }
    setActionError(null);
    try {
      await deleteAdminSubcategory(subcategory.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to delete subcategory.",
      );
    }
  };

  const handleRestore = async (subcategory: AdminSubcategory) => {
    setActionError(null);
    try {
      await restoreAdminSubcategory(subcategory.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to restore subcategory.",
      );
    }
  };

  const handleToggleActive = async (subcategory: AdminSubcategory) => {
    setActionError(null);
    try {
      await updateAdminSubcategory(subcategory.id, {
        name: subcategory.name,
        category_id: subcategory.categoryId,
        slug: subcategory.slug,
        image: subcategory.image,
        description: subcategory.description || null,
        sort_order: subcategory.sortOrder,
        is_active: !subcategory.isActive,
      });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to update subcategory status.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Subcategory name is required.");
      return;
    }
    if (!form.departmentId) {
      setActionError("Department is required.");
      return;
    }
    if (!form.categoryId) {
      setActionError("Category is required.");
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = {
      name: form.name.trim(),
      category_id: form.categoryId,
      slug: form.slug.trim() || null,
      image: form.image.trim() || null,
      description: form.description.trim() || null,
      sort_order: Number.isFinite(form.sortOrder) ? form.sortOrder : 0,
      is_active: form.isActive,
    };

    try {
      if (form.id) {
        await updateAdminSubcategory(form.id, payload);
      } else {
        await createAdminSubcategory(payload);
      }
      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save subcategory.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Subcategories</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Child categories linked via parent_id. Filter by department and category.
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
            Add subcategory
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
            {form.id ? "Edit subcategory" : "New subcategory"}
          </h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label className="admin-label" htmlFor="subcategory-department">
                Department *
              </label>
              <select
                id="subcategory-department"
                className="admin-input mt-1.5"
                value={form.departmentId}
                onChange={(event) => {
                  const departmentId = event.target.value;
                  const firstCategory =
                    rootCategories.find((category) => category.departmentId === departmentId)
                      ?.id ?? "";
                  setForm({ ...form, departmentId, categoryId: firstCategory });
                }}
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
              <label className="admin-label" htmlFor="subcategory-category">
                Category *
              </label>
              <select
                id="subcategory-category"
                className="admin-input mt-1.5"
                value={form.categoryId}
                onChange={(event) => setForm({ ...form, categoryId: event.target.value })}
              >
                <option value="">Select category</option>
                {categoriesForForm.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="subcategory-name">
                Name *
              </label>
              <input
                id="subcategory-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="subcategory-slug">
                Slug
              </label>
              <input
                id="subcategory-slug"
                className="admin-input mt-1.5"
                placeholder="auto from category + name if empty"
                value={form.slug}
                onChange={(event) => setForm({ ...form, slug: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="subcategory-image">
                Image URL
              </label>
              <input
                id="subcategory-image"
                className="admin-input mt-1.5"
                placeholder="https://… or storage path"
                value={form.image}
                onChange={(event) => setForm({ ...form, image: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="subcategory-sort-order">
                Sort order
              </label>
              <input
                id="subcategory-sort-order"
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
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="subcategory-description">
                Description
              </label>
              <textarea
                id="subcategory-description"
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
            placeholder="Search subcategories…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input w-auto"
            value={departmentFilter}
            onChange={(event) => {
              setDepartmentFilter(event.target.value);
              setCategoryFilter("all");
            }}
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
            value={categoryFilter}
            onChange={(event) => setCategoryFilter(event.target.value)}
          >
            <option value="all">All categories</option>
            {categoriesForFilter.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
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
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            Loading subcategories…
          </div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load subcategories</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : filteredSubcategories.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No subcategories configured.
          </div>
        ) : (
          <>
            <ul className="divide-y divide-zinc-100">
              {pagedSubcategories.map((subcategory) => (
                <li
                  key={subcategory.id}
                  className="flex flex-wrap items-start gap-3 px-5 py-3"
                >
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {subcategory.name}
                      {!subcategory.isActive ? (
                        <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                          Inactive
                        </span>
                      ) : null}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {subcategory.departmentIcon ? `${subcategory.departmentIcon} ` : ""}
                      {subcategory.departmentName ?? "No department"} →{" "}
                      {subcategory.categoryName} · {subcategory.slug} · sort{" "}
                      {subcategory.sortOrder}
                    </p>
                    {subcategory.description ? (
                      <p className="mt-1 text-xs text-zinc-600">{subcategory.description}</p>
                    ) : null}
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => void handleToggleActive(subcategory)}
                    >
                      {subcategory.isActive ? "Deactivate" : "Activate"}
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(subcategory)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                      onClick={() => void handleDelete(subcategory)}
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
                  Page {currentPage} of {totalPages} · {filteredSubcategories.length} total
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
            <h2 className="text-sm font-semibold text-zinc-900">Deleted subcategories</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((subcategory) => (
                <li key={subcategory.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{subcategory.name}</p>
                    <p className="text-xs text-zinc-500">{subcategory.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(subcategory)}
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
