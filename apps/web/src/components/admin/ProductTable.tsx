"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import type { Product, ProductOrigin, ProductStatus } from "@/lib/types/catalog";
import { formatAdminDate, getProductThumbnail } from "@/lib/admin/product-utils";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { formatPrice } from "@/lib/catalog/utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { EditIcon, EyeIcon, TrashIcon, StarOutlineIcon } from "@/components/home/icons";
import { DeleteProductModal } from "./DeleteProductModal";
import { StatusBadge } from "./StatusBadge";
import { AdminFilterChips } from "@/components/admin/AdminFilterChips";
import { ADMIN_SEARCH_DEBOUNCE_MS } from "@/lib/admin/admin-search-utils";
import {
  extractAdminProductFilterOptions,
  filterAdminProducts,
} from "@/lib/admin/product-query-filters";
import { useDebouncedValue } from "@/hooks/use-debounced-value";

interface ProductTableProps {
  products: Product[];
  isHydrated?: boolean;
  error?: string | null;
  onRetry?: () => void;
  onDelete: (id: number) => void | Promise<void>;
  onBulkDelete: (ids: number[]) => void | Promise<void>;
  initialCategory?: string;
  initialBrand?: string;
  initialSearch?: string;
}

type StatusFilter = "all" | ProductStatus;
type OriginFilter = "all" | ProductOrigin;

const PAGE_SIZE = 10;

function originLabel(origin: ProductOrigin) {
  return origin === "china" ? "China" : "Buy From TZ";
}

export function ProductTable({
  products,
  isHydrated = true,
  error = null,
  onRetry,
  onDelete,
  onBulkDelete,
  initialCategory,
  initialBrand,
  initialSearch,
}: ProductTableProps) {
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);
  const [bulkDeleteIds, setBulkDeleteIds] = useState<number[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [search, setSearch] = useState(initialSearch ?? "");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [categoryFilter, setCategoryFilter] = useState(initialCategory ?? "all");
  const [brandFilter, setBrandFilter] = useState(initialBrand ?? "all");
  const [originFilter, setOriginFilter] = useState<OriginFilter>("all");
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search, ADMIN_SEARCH_DEBOUNCE_MS);

  const filterOptions = useMemo(() => extractAdminProductFilterOptions(products), [products]);

  const filtered = useMemo(
    () =>
      filterAdminProducts(products, {
        search: debouncedSearch,
        status: statusFilter,
        origin: originFilter,
        category: categoryFilter,
        brand: brandFilter,
      }),
    [products, debouncedSearch, statusFilter, originFilter, categoryFilter, brandFilter],
  );

  const isSearchPending = search.trim() !== debouncedSearch.trim();

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter, originFilter, categoryFilter, brandFilter]);

  useEffect(() => {
    if (initialCategory) {
      setCategoryFilter(initialCategory);
    }
    if (initialBrand) {
      setBrandFilter(initialBrand);
    }
    if (initialSearch) {
      setSearch(initialSearch);
    }
  }, [initialCategory, initialBrand, initialSearch]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const currentPage = Math.min(page, totalPages);
  const paginated = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

  const allVisibleSelected =
    paginated.length > 0 && paginated.every((product) => selectedIds.has(product.id));

  const toggleSelectAll = () => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (allVisibleSelected) {
        paginated.forEach((product) => next.delete(product.id));
      } else {
        paginated.forEach((product) => next.add(product.id));
      }
      return next;
    });
  };

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleConfirmDelete = () => {
    if (deleteTarget) {
      void onDelete(deleteTarget.id);
      setDeleteTarget(null);
      setSelectedIds((prev) => {
        const next = new Set(prev);
        next.delete(deleteTarget.id);
        return next;
      });
    }
  };

  const handleConfirmBulkDelete = () => {
    void onBulkDelete(bulkDeleteIds);
    setBulkDeleteIds([]);
    setSelectedIds(new Set());
  };

  if (!isHydrated) {
    return (
      <div className="admin-card flex items-center justify-center py-16 text-sm text-zinc-500">
        Loading products…
      </div>
    );
  }

  if (error) {
    return (
      <div className="admin-card flex flex-col items-center py-16 text-center">
        <p className="text-sm font-medium text-zinc-700">Unable to load products</p>
        <p className="mt-1 max-w-md text-xs text-zinc-500">{error}</p>
        {onRetry ? (
          <button
            type="button"
            onClick={onRetry}
            className="mt-5 inline-flex rounded-lg bg-[#c9a227] px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
          >
            Try again
          </button>
        ) : null}
      </div>
    );
  }

  if (products.length === 0) {
    return (
      <div className="admin-card flex flex-col items-center py-16 text-center">
        <span className="text-4xl">📦</span>
        <p className="mt-4 text-sm font-medium text-zinc-700">No products yet</p>
        <p className="mt-1 text-xs text-zinc-500">Add your first product to get started.</p>
        <Link
          href="/admin/products/new"
          className="mt-5 inline-flex rounded-lg bg-[#c9a227] px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
        >
          Add product
        </Link>
      </div>
    );
  }

  return (
    <>
      <div className="admin-card overflow-hidden">
        <div className="space-y-4 border-b border-zinc-200 p-4">
          <div className="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div className="relative max-w-md flex-1">
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search product, category, brand, or SKU"
                className="admin-input w-full"
                aria-label="Search products by name, category, or brand"
              />
              {isSearchPending && (
                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-medium text-zinc-400">
                  …
                </span>
              )}
            </div>
            <div className="flex items-center gap-3 xl:ml-auto">
              {selectedIds.size > 0 && (
                <button
                  type="button"
                  onClick={() => setBulkDeleteIds([...selectedIds])}
                  className="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100"
                >
                  Delete selected ({selectedIds.size})
                </button>
              )}
              <p className="text-xs text-zinc-500">
                {filtered.length} of {products.length} products
              </p>
            </div>
          </div>

          <AdminFilterChips
            chips={[
              { id: "all", label: "All Statuses" },
              { id: "active", label: "Active" },
              { id: "draft", label: "Draft" },
              { id: "hidden", label: "Inactive" },
            ]}
            activeId={statusFilter}
            onChange={(id) => setStatusFilter(id as StatusFilter)}
            ariaLabel="Product status filters"
          />

          <AdminFilterChips
            chips={[
              { id: "all", label: "All Origins" },
              { id: "china", label: "China Orders" },
              { id: "tz", label: "Buy from Dar" },
            ]}
            activeId={originFilter}
            onChange={(id) => setOriginFilter(id as OriginFilter)}
            ariaLabel="Product origin filters"
          />

          {filterOptions.brands.length > 0 && (
            <AdminFilterChips
              chips={[
                { id: "all", label: "All Brands" },
                ...filterOptions.brands.map((brand) => ({
                  id: brand.slug,
                  label: brand.label,
                })),
              ]}
              activeId={brandFilter}
              onChange={setBrandFilter}
              ariaLabel="Brand filters"
            />
          )}

          {filterOptions.categories.length > 0 && (
            <AdminFilterChips
              chips={[
                { id: "all", label: "All Categories" },
                ...filterOptions.categories.map((category) => ({
                  id: category.slug,
                  label: category.label,
                })),
              ]}
              activeId={categoryFilter}
              onChange={setCategoryFilter}
              ariaLabel="Category filters"
            />
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[1400px] text-left text-sm">
            <thead>
              <tr className="border-b border-zinc-200 bg-zinc-50/80">
                <th className="px-4 py-3">
                  <input
                    type="checkbox"
                    checked={allVisibleSelected}
                    onChange={toggleSelectAll}
                    aria-label="Select all on page"
                    className="rounded border-zinc-300"
                  />
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Image
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Product name
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Brand
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Category
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Origin
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Price
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Air cost
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Sea cost
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Stock
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Status
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Featured
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Created
                </th>
                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {paginated.length === 0 ? (
                <tr>
                  <td colSpan={14} className="px-4 py-12 text-center text-sm text-zinc-500">
                    No products match your filters.
                  </td>
                </tr>
              ) : (
                paginated.map((product) => {
                  const category = getCategoryBySlug(product.categorySlug);
                  const onSale = product.oldPrice > product.price;

                  return (
                    <tr key={product.id} className="transition hover:bg-zinc-50/80">
                      <td className="px-4 py-3">
                        <input
                          type="checkbox"
                          checked={selectedIds.has(product.id)}
                          onChange={() => toggleSelect(product.id)}
                          aria-label={`Select ${product.name}`}
                          className="rounded border-zinc-300"
                        />
                      </td>
                      <td className="px-4 py-3">
                        <div className="h-10 w-10 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                          <ProductImageDisplay
                            image={getProductThumbnail(product)}
                            fallbackEmoji={product.emoji}
                            fallbackGradient={product.gradient}
                            className="h-full w-full"
                            emojiClassName="text-base"
                          />
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="min-w-0 max-w-[220px]">
                          <Link
                            href={`/admin/products/${product.id}`}
                            className="truncate font-medium text-zinc-900 hover:text-[#8b6914] hover:underline"
                          >
                            {product.name}
                          </Link>
                          <p className="truncate text-xs text-zinc-400">{product.slug}</p>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-600">
                        {product.brand ?? "—"}
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-600">
                        {category?.name ?? product.categorySlug}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium ${
                            product.origin === "china"
                              ? "bg-red-50 text-red-700"
                              : "bg-emerald-50 text-emerald-700"
                          }`}
                        >
                          {originLabel(product.origin)}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div>
                          <span className="font-medium text-zinc-900">
                            {formatPrice(product.price)}
                          </span>
                          {onSale && (
                            <span className="ml-1.5 text-xs text-zinc-400 line-through">
                              {formatPrice(product.oldPrice)}
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-600">
                        {product.origin === "china" && product.airCost
                          ? formatPrice(product.airCost)
                          : "—"}
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-600">
                        {product.origin === "china" && product.seaCost
                          ? formatPrice(product.seaCost)
                          : "—"}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`text-xs font-medium ${
                            product.stock <= 0
                              ? "text-red-600"
                              : product.stock <= 10
                                ? "text-amber-600"
                                : "text-zinc-600"
                          }`}
                        >
                          {product.stock <= 0 ? "Out of stock" : product.stock}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <StatusBadge status={product.status} />
                      </td>
                      <td className="px-4 py-3">
                        {product.featured ? (
                          <StarOutlineIcon className="h-4 w-4 fill-[#c9a227] text-[#c9a227]" />
                        ) : (
                          <span className="text-xs text-zinc-300">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-500">
                        {formatAdminDate(product.createdAt)}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-1">
                          <Link
                            href={`/admin/products/${product.id}`}
                            className="flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800"
                            aria-label={`View ${product.name}`}
                          >
                            <EyeIcon className="h-4 w-4" />
                          </Link>
                          <Link
                            href={`/admin/products/${product.id}/edit`}
                            className="flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800"
                            aria-label={`Edit ${product.name}`}
                          >
                            <EditIcon className="h-4 w-4" />
                          </Link>
                          <button
                            type="button"
                            onClick={() => setDeleteTarget(product)}
                            className="flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-red-50 hover:text-red-600"
                            aria-label={`Delete ${product.name}`}
                          >
                            <TrashIcon className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {filtered.length > PAGE_SIZE && (
          <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
            <p className="text-xs text-zinc-500">
              Page {currentPage} of {totalPages}
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                disabled={currentPage <= 1}
                onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-40"
              >
                Previous
              </button>
              <button
                type="button"
                disabled={currentPage >= totalPages}
                onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
                className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {deleteTarget !== null && (
        <DeleteProductModal
          product={deleteTarget}
          onConfirm={handleConfirmDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}

      {bulkDeleteIds.length > 0 && (
        <DeleteProductModal
          product={null}
          count={bulkDeleteIds.length}
          onConfirm={handleConfirmBulkDelete}
          onCancel={() => setBulkDeleteIds([])}
        />
      )}
    </>
  );
}
