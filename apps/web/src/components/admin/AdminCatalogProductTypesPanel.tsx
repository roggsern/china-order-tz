"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminCatalogProductType,
  deleteAdminCatalogProductType,
  fetchAdminCatalogProductTypes,
  fetchAdminCategories,
  fetchAdminDepartments,
  fetchAdminSubcategories,
  restoreAdminCatalogProductType,
  updateAdminCatalogProductType,
  type AdminCatalogProductType,
  type AdminCategory,
  type AdminDepartment,
  type AdminSubcategory,
} from "@/lib/api/admin-catalog";

type FormState = {
  id?: string;
  departmentId: string;
  categoryId: string;
  subcategoryId: string;
  name: string;
  slug: string;
  image: string;
  description: string;
  sortOrder: number;
  isActive: boolean;
};

const emptyForm = (
  departmentId = "",
  categoryId = "",
  subcategoryId = "",
): FormState => ({
  departmentId,
  categoryId,
  subcategoryId,
  name: "",
  slug: "",
  image: "",
  description: "",
  sortOrder: 0,
  isActive: true,
});

const PAGE_SIZE = 15;

export function AdminCatalogProductTypesPanel() {
  const [items, setItems] = useState<AdminCatalogProductType[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [subcategories, setSubcategories] = useState<AdminSubcategory[]>([]);
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [trashed, setTrashed] = useState<AdminCatalogProductType[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [departmentFilter, setDepartmentFilter] = useState("all");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [subcategoryFilter, setSubcategoryFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [page, setPage] = useState(1);
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<FormState | null>(null);
  const [saving, setSaving] = useState(false);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [nextItems, deleted, nextCategories, nextSubcategories, nextDepartments] =
        await Promise.all([
          fetchAdminCatalogProductTypes(),
          fetchAdminCatalogProductTypes({ trashed: true }),
          fetchAdminCategories({ rootsOnly: true }),
          fetchAdminSubcategories(),
          fetchAdminDepartments(),
        ]);
      setItems(nextItems);
      setTrashed(deleted);
      setCategories(nextCategories.filter((item) => !item.parentId && item.departmentId));
      setSubcategories(nextSubcategories);
      setDepartments(nextDepartments);
    } catch (err) {
      setItems([]);
      setTrashed([]);
      setCategories([]);
      setSubcategories([]);
      setDepartments([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load product types from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const categoriesForFilter = useMemo(() => {
    if (departmentFilter === "all") return categories;
    return categories.filter((category) => category.departmentId === departmentFilter);
  }, [categories, departmentFilter]);

  const subcategoriesForFilter = useMemo(() => {
    return subcategories.filter((subcategory) => {
      if (departmentFilter !== "all" && subcategory.departmentId !== departmentFilter) {
        return false;
      }
      if (categoryFilter !== "all" && subcategory.categoryId !== categoryFilter) {
        return false;
      }
      return true;
    });
  }, [subcategories, departmentFilter, categoryFilter]);

  const categoriesForForm = useMemo(() => {
    if (!form?.departmentId) return [];
    return categories.filter((category) => category.departmentId === form.departmentId);
  }, [categories, form?.departmentId]);

  const subcategoriesForForm = useMemo(() => {
    if (!form?.categoryId) return [];

    const hasActiveChild = (id: string) =>
      categories.some((category) => category.parentId === id && category.isActive) ||
      subcategories.some(
        (subcategory) => subcategory.categoryId === id && subcategory.isActive,
      );

    const children = subcategories.filter(
      (subcategory) =>
        subcategory.categoryId === form.categoryId && subcategory.isActive,
    );
    const leafChildren = children.filter((subcategory) => !hasActiveChild(subcategory.id));

    if (leafChildren.length > 0) {
      return leafChildren;
    }

    // Leaf root category: no active children — attach CPT to the category itself.
    const category = categories.find((item) => item.id === form.categoryId);
    if (!category || !category.isActive || hasActiveChild(category.id)) {
      return [];
    }

    return [
      {
        id: category.id,
        categoryId: category.id,
        categoryName: category.name,
        categorySlug: category.slug,
        departmentId: category.departmentId ?? null,
        departmentName: category.departmentName ?? null,
        departmentIcon: category.departmentIcon ?? null,
        name: `${category.name} (leaf category)`,
        slug: category.slug,
        description: "",
        image: null,
        sortOrder: 0,
        isActive: category.isActive,
        productsCount: 0,
      } satisfies AdminSubcategory,
    ];
  }, [subcategories, categories, form?.categoryId]);

  const filteredItems = useMemo(() => {
    const q = search.trim().toLowerCase();
    return items
      .filter((item) => {
        if (departmentFilter !== "all" && item.departmentId !== departmentFilter) return false;
        if (categoryFilter !== "all" && item.categoryId !== categoryFilter) return false;
        if (subcategoryFilter !== "all" && item.subcategoryId !== subcategoryFilter) {
          return false;
        }
        if (statusFilter === "active" && !item.isActive) return false;
        if (statusFilter === "inactive" && item.isActive) return false;
        if (!q) return true;
        return (
          item.name.toLowerCase().includes(q) ||
          item.slug.toLowerCase().includes(q) ||
          item.description.toLowerCase().includes(q) ||
          item.subcategoryName.toLowerCase().includes(q) ||
          (item.categoryName ?? "").toLowerCase().includes(q) ||
          (item.departmentName ?? "").toLowerCase().includes(q)
        );
      })
      .sort((a, b) => {
        const deptCmp = (a.departmentName ?? "").localeCompare(b.departmentName ?? "");
        if (deptCmp !== 0) return deptCmp;
        const catCmp = (a.categoryName ?? "").localeCompare(b.categoryName ?? "");
        if (catCmp !== 0) return catCmp;
        const subCmp = a.subcategoryName.localeCompare(b.subcategoryName);
        if (subCmp !== 0) return subCmp;
        const sortCmp = a.sortOrder - b.sortOrder;
        if (sortCmp !== 0) return sortCmp;
        return a.name.localeCompare(b.name);
      });
  }, [
    items,
    search,
    departmentFilter,
    categoryFilter,
    subcategoryFilter,
    statusFilter,
  ]);

  const totalPages = Math.max(1, Math.ceil(filteredItems.length / PAGE_SIZE));
  const currentPage = Math.min(page, totalPages);
  const pagedItems = filteredItems.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE,
  );

  useEffect(() => {
    setPage(1);
  }, [search, departmentFilter, categoryFilter, subcategoryFilter, statusFilter]);

  useEffect(() => {
    if (categoryFilter === "all") return;
    if (!categoriesForFilter.some((category) => category.id === categoryFilter)) {
      setCategoryFilter("all");
      setSubcategoryFilter("all");
    }
  }, [categoriesForFilter, categoryFilter]);

  useEffect(() => {
    if (subcategoryFilter === "all") return;
    if (!subcategoriesForFilter.some((item) => item.id === subcategoryFilter)) {
      setSubcategoryFilter("all");
    }
  }, [subcategoriesForFilter, subcategoryFilter]);

  // Keep leaf parent selection valid when category options change.
  useEffect(() => {
    if (!form) return;
    if (subcategoriesForForm.length === 0) {
      if (form.subcategoryId !== "") {
        setForm((current) => (current ? { ...current, subcategoryId: "" } : current));
      }
      return;
    }
    const stillValid = subcategoriesForForm.some((item) => item.id === form.subcategoryId);
    if (!stillValid) {
      const nextId = subcategoriesForForm[0]?.id ?? "";
      setForm((current) =>
        current ? { ...current, subcategoryId: nextId } : current,
      );
    }
  }, [form, subcategoriesForForm]);

  const openCreate = () => {
    setActionError(null);
    const defaultDepartmentId =
      departmentFilter !== "all" ? departmentFilter : departments[0]?.id ?? "";
    const defaultCategoryId =
      categoryFilter !== "all"
        ? categoryFilter
        : categories.find((category) => category.departmentId === defaultDepartmentId)?.id ??
          "";
    // Prefer an explicit subcategory filter when it is a leaf; otherwise leave blank
    // for the leaf-selector effect to fill from DB-backed options.
    const defaultSubcategoryId =
      subcategoryFilter !== "all" ? subcategoryFilter : "";
    setForm(emptyForm(defaultDepartmentId, defaultCategoryId, defaultSubcategoryId));
  };

  const openEdit = (item: AdminCatalogProductType) => {
    setActionError(null);
    setForm({
      id: item.id,
      departmentId: item.departmentId ?? "",
      categoryId: item.categoryId ?? "",
      subcategoryId: item.subcategoryId,
      name: item.name,
      slug: item.slug,
      image: item.image ?? "",
      description: item.description,
      sortOrder: item.sortOrder,
      isActive: item.isActive,
    });
  };

  const handleDelete = async (item: AdminCatalogProductType) => {
    if ((item.productsCount ?? 0) > 0) {
      setActionError(
        `This Catalog Product Type is used by ${item.productsCount} products. Reassign or remove those products before deleting it.`,
      );
      return;
    }
    if (
      !window.confirm(`Delete product type “${item.name}”? You can restore it later.`)
    ) {
      return;
    }
    setActionError(null);
    try {
      await deleteAdminCatalogProductType(item.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to delete product type.",
      );
    }
  };

  const handleRestore = async (item: AdminCatalogProductType) => {
    setActionError(null);
    try {
      await restoreAdminCatalogProductType(item.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to restore product type.",
      );
    }
  };

  const handleToggleActive = async (item: AdminCatalogProductType) => {
    setActionError(null);
    try {
      await updateAdminCatalogProductType(item.id, {
        name: item.name,
        subcategory_id: item.subcategoryId,
        slug: item.slug,
        image: item.image,
        description: item.description || null,
        sort_order: item.sortOrder,
        is_active: !item.isActive,
      });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to update product type status.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Product type name is required.");
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
    if (!form.subcategoryId) {
      setActionError(
        subcategoriesForForm.length === 0
          ? "Select a leaf category (a category with no active children)."
          : "Leaf category is required.",
      );
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = {
      name: form.name.trim(),
      subcategory_id: form.subcategoryId,
      slug: form.slug.trim() || null,
      image: form.image.trim() || null,
      description: form.description.trim() || null,
      sort_order: Number.isFinite(form.sortOrder) ? form.sortOrder : 0,
      is_active: form.isActive,
    };

    try {
      if (form.id) {
        await updateAdminCatalogProductType(form.id, payload);
      } else {
        await createAdminCatalogProductType(payload);
      }
      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save product type.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Product Types</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Catalog Product Types — taxonomy leaf under Department → Category →
            Subcategory. Distinct from Configuration Templates (variant/SKU schema).
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
            Add product type
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
            {form.id ? "Edit product type" : "New product type"}
          </h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label className="admin-label" htmlFor="pt-department">
                Department *
              </label>
              <select
                id="pt-department"
                className="admin-input mt-1.5"
                value={form.departmentId}
                onChange={(event) => {
                  const departmentId = event.target.value;
                  const firstCategory =
                    categories.find((category) => category.departmentId === departmentId)
                      ?.id ?? "";
                  const firstSub =
                    subcategories.find((item) => item.categoryId === firstCategory)?.id ??
                    firstCategory;
                  setForm({
                    ...form,
                    departmentId,
                    categoryId: firstCategory,
                    subcategoryId: firstSub,
                  });
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
              <label className="admin-label" htmlFor="pt-category">
                Parent category *
              </label>
              <select
                id="pt-category"
                className="admin-input mt-1.5"
                value={form.categoryId}
                onChange={(event) => {
                  const categoryId = event.target.value;
                  setForm({ ...form, categoryId, subcategoryId: "" });
                }}
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
              <label className="admin-label" htmlFor="pt-subcategory">
                Leaf category *
              </label>
              <select
                id="pt-subcategory"
                className="admin-input mt-1.5"
                value={form.subcategoryId}
                onChange={(event) => setForm({ ...form, subcategoryId: event.target.value })}
                disabled={!form.categoryId || subcategoriesForForm.length === 0}
              >
                <option value="">
                  {!form.categoryId
                    ? "Select a parent category first"
                    : subcategoriesForForm.length === 0
                      ? "No leaf category available"
                      : "Select leaf category"}
                </option>
                {subcategoriesForForm.map((subcategory) => (
                  <option key={subcategory.id} value={subcategory.id}>
                    {subcategory.name}
                  </option>
                ))}
              </select>
              {form.categoryId && subcategoriesForForm.length === 0 ? (
                <p className="mt-1.5 text-xs text-amber-700">
                  No leaf category under this parent. Choose a category with no active children,
                  or create a subcategory first.
                </p>
              ) : (
                <p className="mt-1.5 text-xs text-zinc-500">
                  Catalog Product Types attach only to leaf categories (no active children).
                </p>
              )}
            </div>
            <div>
              <label className="admin-label" htmlFor="pt-sort-order">
                Sort order
              </label>
              <input
                id="pt-sort-order"
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
              <label className="admin-label" htmlFor="pt-name">
                Product type name *
              </label>
              <input
                id="pt-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="pt-slug">
                Slug
              </label>
              <input
                id="pt-slug"
                className="admin-input mt-1.5"
                placeholder="auto from subcategory + name if empty"
                value={form.slug}
                onChange={(event) => setForm({ ...form, slug: event.target.value })}
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="pt-image">
                Image URL
              </label>
              <input
                id="pt-image"
                className="admin-input mt-1.5"
                placeholder="https://… or storage path"
                value={form.image}
                onChange={(event) => setForm({ ...form, image: event.target.value })}
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="pt-description">
                Description
              </label>
              <textarea
                id="pt-description"
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
            placeholder="Search product types…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input w-auto"
            value={departmentFilter}
            onChange={(event) => {
              setDepartmentFilter(event.target.value);
              setCategoryFilter("all");
              setSubcategoryFilter("all");
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
            onChange={(event) => {
              setCategoryFilter(event.target.value);
              setSubcategoryFilter("all");
            }}
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
            value={subcategoryFilter}
            onChange={(event) => setSubcategoryFilter(event.target.value)}
          >
            <option value="all">All subcategories</option>
            {subcategoriesForFilter.map((subcategory) => (
              <option key={subcategory.id} value={subcategory.id}>
                {subcategory.name}
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
            Loading product types…
          </div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load product types</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : filteredItems.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No product types configured.
          </div>
        ) : (
          <>
            <ul className="divide-y divide-zinc-100">
              {pagedItems.map((item) => (
                <li key={item.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {item.name}
                      {!item.isActive ? (
                        <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                          Inactive
                        </span>
                      ) : null}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {item.departmentIcon ? `${item.departmentIcon} ` : ""}
                      {item.departmentName ?? "No department"} → {item.categoryName ?? "—"} →{" "}
                      {item.subcategoryName} · {item.slug} · sort {item.sortOrder}
                    </p>
                    <p className="mt-0.5 text-xs text-zinc-500">
                      {item.productsCount} product{item.productsCount === 1 ? "" : "s"} ·{" "}
                      {item.attributesCount} attribute
                      {item.attributesCount === 1 ? "" : "s"}
                    </p>
                    {item.description ? (
                      <p className="mt-1 text-xs text-zinc-600">{item.description}</p>
                    ) : null}
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => void handleToggleActive(item)}
                    >
                      {item.isActive ? "Deactivate" : "Activate"}
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(item)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                      disabled={(item.productsCount ?? 0) > 0}
                      title={
                        (item.productsCount ?? 0) > 0
                          ? `Used by ${item.productsCount} products — reassign before deleting`
                          : "Delete product type"
                      }
                      onClick={() => void handleDelete(item)}
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
                  Page {currentPage} of {totalPages} · {filteredItems.length} total
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
            <h2 className="text-sm font-semibold text-zinc-900">Deleted product types</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((item) => (
                <li key={item.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{item.name}</p>
                    <p className="text-xs text-zinc-500">{item.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(item)}
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
