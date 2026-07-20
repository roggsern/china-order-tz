"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminBrand,
  deleteAdminBrand,
  fetchAdminBrands,
  fetchAdminBrandsPage,
  fetchAdminCategories,
  restoreAdminBrand,
  updateAdminBrand,
  uploadAdminBrandAsset,
  type AdminBrand,
  type AdminCategory,
} from "@/lib/api/admin-catalog";

type BrandFormState = {
  id?: string;
  name: string;
  description: string;
  logo: string;
  banner: string;
  website: string;
  country: string;
  sortOrder: number;
  isFeatured: boolean;
  isActive: boolean;
  categoryIds: string[];
  logoFile: File | null;
  bannerFile: File | null;
};

const emptyForm = (): BrandFormState => ({
  name: "",
  description: "",
  logo: "",
  banner: "",
  website: "",
  country: "",
  sortOrder: 0,
  isFeatured: false,
  isActive: true,
  categoryIds: [],
  logoFile: null,
  bannerFile: null,
});

const PAGE_SIZE = 15;

function categoryLabel(category: AdminCategory, byId: Map<string, AdminCategory>): string {
  const parts = [category.name];
  let parentId = category.parentId;
  while (parentId && byId.has(parentId)) {
    const parent = byId.get(parentId)!;
    parts.unshift(parent.name);
    parentId = parent.parentId;
  }
  const origin = category.origin ? ` [${category.origin}]` : "";
  return `${parts.join(" → ")}${origin}`;
}

export function AdminBrandsPanel() {
  const [brands, setBrands] = useState<AdminBrand[]>([]);
  const [trashed, setTrashed] = useState<AdminBrand[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [featuredFilter, setFeaturedFilter] = useState<"all" | "featured" | "standard">("all");
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<BrandFormState | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter, featuredFilter]);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [pageResult, deleted, nextCategories] = await Promise.all([
        fetchAdminBrandsPage({
          page,
          perPage: PAGE_SIZE,
          search: debouncedSearch || undefined,
          isActive:
            statusFilter === "active" ? true : statusFilter === "inactive" ? false : undefined,
          isFeatured:
            featuredFilter === "featured"
              ? true
              : featuredFilter === "standard"
                ? false
                : undefined,
        }),
        fetchAdminBrands({ trashed: true }),
        fetchAdminCategories(),
      ]);
      setBrands(pageResult.items);
      setTotal(pageResult.total);
      setLastPage(pageResult.lastPage);
      setTrashed(deleted);
      setCategories(nextCategories);
    } catch (err) {
      setBrands([]);
      setTrashed([]);
      setCategories([]);
      setTotal(0);
      setLastPage(1);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load brands from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [page, debouncedSearch, statusFilter, featuredFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const categoriesById = useMemo(() => {
    const map = new Map<string, AdminCategory>();
    for (const category of categories) {
      map.set(category.id, category);
    }
    return map;
  }, [categories]);

  const categoryOptions = useMemo(() => {
    return [...categories]
      .sort((a, b) => categoryLabel(a, categoriesById).localeCompare(categoryLabel(b, categoriesById)))
      .map((category) => ({
        id: category.id,
        label: categoryLabel(category, categoriesById),
      }));
  }, [categories, categoriesById]);

  const openCreate = () => {
    setActionError(null);
    setForm({
      ...emptyForm(),
      sortOrder: total + 1,
    });
  };

  const openEdit = (brand: AdminBrand) => {
    setActionError(null);
    setForm({
      id: brand.id,
      name: brand.name,
      description: brand.description,
      logo: brand.logo ?? "",
      banner: brand.banner ?? "",
      website: brand.website ?? "",
      country: brand.country ?? "",
      sortOrder: brand.sortOrder,
      isFeatured: brand.isFeatured,
      isActive: brand.isActive,
      categoryIds: [...brand.categoryIds],
      logoFile: null,
      bannerFile: null,
    });
  };

  const toggleCategoryLink = (categoryId: string) => {
    if (!form) return;
    const exists = form.categoryIds.includes(categoryId);
    setForm({
      ...form,
      categoryIds: exists
        ? form.categoryIds.filter((id) => id !== categoryId)
        : [...form.categoryIds, categoryId],
    });
  };

  const handleDelete = async (brand: AdminBrand) => {
    if (!window.confirm(`Delete brand “${brand.name}”? You can restore it later.`)) {
      return;
    }
    setActionError(null);
    try {
      await deleteAdminBrand(brand.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to delete brand.",
      );
    }
  };

  const handleRestore = async (brand: AdminBrand) => {
    setActionError(null);
    try {
      await restoreAdminBrand(brand.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to restore brand.",
      );
    }
  };

  const handleToggleActive = async (brand: AdminBrand) => {
    setActionError(null);
    try {
      await updateAdminBrand(brand.id, {
        name: brand.name,
        is_active: !brand.isActive,
      });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to update brand status.",
      );
    }
  };

  const handleToggleFeatured = async (brand: AdminBrand) => {
    setActionError(null);
    try {
      await updateAdminBrand(brand.id, {
        name: brand.name,
        is_featured: !brand.isFeatured,
      });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to update featured flag.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Brand name is required.");
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      logo: form.logo.trim() || null,
      banner: form.banner.trim() || null,
      website: form.website.trim() || null,
      country: form.country.trim() || null,
      is_featured: form.isFeatured,
      sort_order: form.sortOrder,
      is_active: form.isActive,
      category_ids: form.categoryIds,
    };

    try {
      let brandId = form.id;
      if (form.id) {
        await updateAdminBrand(form.id, payload);
      } else {
        const created = await createAdminBrand(payload);
        brandId = created.id;
      }

      if (brandId && form.logoFile) {
        await uploadAdminBrandAsset(brandId, "logo", form.logoFile);
      }
      if (brandId && form.bannerFile) {
        await uploadAdminBrandAsset(brandId, "banner", form.bannerFile);
      }

      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to save brand.",
      );
    } finally {
      setSaving(false);
    }
  };

  const currentPage = Math.min(page, Math.max(1, lastPage));

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Brands</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Shared catalog brands. Not tied to departments — optionally link categories when needed.
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
            Add brand
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
            {form.id ? "Edit brand" : "New brand"}
          </h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="brand-name">
                Name *
              </label>
              <input
                id="brand-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="brand-description">
                Description
              </label>
              <textarea
                id="brand-description"
                className="admin-input mt-1.5 min-h-[80px]"
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="brand-logo">
                Logo URL
              </label>
              <input
                id="brand-logo"
                className="admin-input mt-1.5"
                placeholder="https://… or storage path"
                value={form.logo}
                onChange={(event) => setForm({ ...form, logo: event.target.value })}
              />
              <label className="admin-label mt-2 block" htmlFor="brand-logo-file">
                Logo upload
              </label>
              <input
                id="brand-logo-file"
                type="file"
                accept="image/*"
                className="mt-1.5 block w-full text-xs text-zinc-600"
                onChange={(event) =>
                  setForm({ ...form, logoFile: event.target.files?.[0] ?? null })
                }
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="brand-banner">
                Banner URL
              </label>
              <input
                id="brand-banner"
                className="admin-input mt-1.5"
                placeholder="https://… or storage path"
                value={form.banner}
                onChange={(event) => setForm({ ...form, banner: event.target.value })}
              />
              <label className="admin-label mt-2 block" htmlFor="brand-banner-file">
                Banner upload
              </label>
              <input
                id="brand-banner-file"
                type="file"
                accept="image/*"
                className="mt-1.5 block w-full text-xs text-zinc-600"
                onChange={(event) =>
                  setForm({ ...form, bannerFile: event.target.files?.[0] ?? null })
                }
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="brand-website">
                Website
              </label>
              <input
                id="brand-website"
                className="admin-input mt-1.5"
                value={form.website}
                onChange={(event) => setForm({ ...form, website: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="brand-country">
                Country
              </label>
              <input
                id="brand-country"
                className="admin-input mt-1.5"
                placeholder="US, CN, TZ…"
                value={form.country}
                onChange={(event) => setForm({ ...form, country: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="brand-sort">
                Sort order
              </label>
              <input
                id="brand-sort"
                type="number"
                min={0}
                className="admin-input mt-1.5"
                value={form.sortOrder}
                onChange={(event) =>
                  setForm({ ...form, sortOrder: Number(event.target.value) || 0 })
                }
              />
            </div>
            <div className="flex flex-col justify-end gap-2 pb-1">
              <label className="flex items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.isFeatured}
                  onChange={(event) => setForm({ ...form, isFeatured: event.target.checked })}
                />
                Featured
              </label>
              <label className="flex items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.isActive}
                  onChange={(event) => setForm({ ...form, isActive: event.target.checked })}
                />
                Active
              </label>
            </div>
          </div>

          <div className="mt-5">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
              Linked categories (optional)
            </h3>
            <p className="mt-1 text-xs text-zinc-500">
              Brands stay shared across the catalog. Category links are optional navigation aids.
            </p>
            {categoryOptions.length === 0 ? (
              <p className="mt-3 text-sm text-zinc-500">No categories configured.</p>
            ) : (
              <div className="mt-3 max-h-56 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-3">
                {categoryOptions.map((option) => (
                  <label
                    key={option.id}
                    className="flex items-start gap-2 rounded px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50"
                  >
                    <input
                      type="checkbox"
                      className="mt-0.5"
                      checked={form.categoryIds.includes(option.id)}
                      onChange={() => toggleCategoryLink(option.id)}
                    />
                    <span>{option.label}</span>
                  </label>
                ))}
              </div>
            )}
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
            placeholder="Search brands…"
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
          <select
            className="admin-input w-auto"
            value={featuredFilter}
            onChange={(event) =>
              setFeaturedFilter(event.target.value as "all" | "featured" | "standard")
            }
          >
            <option value="all">All brands</option>
            <option value="featured">Featured</option>
            <option value="standard">Not featured</option>
          </select>
        </div>

        {isLoading ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">Loading brands…</div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load brands</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : brands.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No brands configured.
          </div>
        ) : (
          <>
            <ul className="divide-y divide-zinc-100">
              {brands.map((brand) => (
                <li key={brand.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded bg-zinc-100 text-lg">
                    {brand.logo ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={brand.logo} alt="" className="h-full w-full object-contain" />
                    ) : (
                      brand.icon
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {brand.name}
                      {brand.isFeatured ? (
                        <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                          Featured
                        </span>
                      ) : null}
                      {!brand.isActive ? (
                        <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                          Inactive
                        </span>
                      ) : null}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {brand.slug}
                      {brand.country ? ` · ${brand.country}` : ""}
                      {` · ${brand.productsCount} products · sort ${brand.sortOrder}`}
                    </p>
                    {brand.description ? (
                      <p className="mt-1 text-xs text-zinc-600">{brand.description}</p>
                    ) : null}
                    <p className="mt-1 text-[11px] text-zinc-500">
                      Linked categories:{" "}
                      {brand.categories.length === 0
                        ? "none"
                        : brand.categories.map((item) => item.name).join(", ")}
                    </p>
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => void handleToggleFeatured(brand)}
                    >
                      {brand.isFeatured ? "Unfeature" : "Feature"}
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => void handleToggleActive(brand)}
                    >
                      {brand.isActive ? "Deactivate" : "Activate"}
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(brand)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                      onClick={() => void handleDelete(brand)}
                    >
                      Delete
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-4 py-3">
              <p className="text-xs text-zinc-500">
                Showing page {currentPage} of {lastPage} · {total} brands
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
                  disabled={currentPage >= lastPage}
                  onClick={() => setPage((value) => Math.min(lastPage, value + 1))}
                >
                  Next
                </button>
              </div>
            </div>
          </>
        )}
      </div>

      {showTrashed ? (
        <div className="admin-card mt-4 overflow-hidden">
          <div className="border-b border-zinc-200 px-5 py-3">
            <h2 className="text-sm font-semibold text-zinc-900">Deleted brands</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((brand) => (
                <li key={brand.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{brand.name}</p>
                    <p className="text-xs text-zinc-500">{brand.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(brand)}
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
