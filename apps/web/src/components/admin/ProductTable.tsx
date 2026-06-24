"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import type { Product, ProductStatus } from "@/lib/types/catalog";
import { formatPrice } from "@/lib/catalog/utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { EditIcon, TrashIcon, StarOutlineIcon } from "@/components/home/icons";
import { DeleteProductModal } from "./DeleteProductModal";
import { StatusBadge } from "./StatusBadge";

interface ProductTableProps {
  products: Product[];
  onDelete: (id: number) => void;
}

type StatusFilter = "all" | ProductStatus;

export function ProductTable({ products, onDelete }: ProductTableProps) {
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [categoryFilter, setCategoryFilter] = useState("all");

  const categories = useMemo(() => {
    const slugs = [...new Set(products.map((p) => p.categorySlug))];
    return slugs.map((slug) => getCategoryBySlug(slug)).filter(Boolean);
  }, [products]);

  const filtered = useMemo(() => {
    const q = search.toLowerCase().trim();
    return products.filter((p) => {
      if (statusFilter !== "all" && p.status !== statusFilter) return false;
      if (categoryFilter !== "all" && p.categorySlug !== categoryFilter) return false;
      if (q && !p.name.toLowerCase().includes(q) && !p.slug.includes(q)) return false;
      return true;
    });
  }, [products, search, statusFilter, categoryFilter]);

  const handleConfirmDelete = () => {
    if (deleteTarget) {
      onDelete(deleteTarget.id);
      setDeleteTarget(null);
    }
  };

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
        {/* Toolbar */}
        <div className="flex flex-col gap-3 border-b border-zinc-200 p-4 sm:flex-row sm:items-center">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Filter products"
            className="admin-input max-w-xs"
            aria-label="Filter products"
          />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value as StatusFilter)}
            className="admin-input max-w-[160px]"
            aria-label="Filter by status"
          >
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="hidden">Hidden</option>
          </select>
          <select
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
            className="admin-input max-w-[200px]"
            aria-label="Filter by category"
          >
            <option value="all">All categories</option>
            {categories.map((cat) =>
              cat ? (
                <option key={cat.slug} value={cat.slug}>
                  {cat.name}
                </option>
              ) : null,
            )}
          </select>
          <p className="ml-auto text-xs text-zinc-500">
            {filtered.length} of {products.length} products
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-left text-sm">
            <thead>
              <tr className="border-b border-zinc-200 bg-zinc-50/80">
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Product
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Status
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Category
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Price
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Stock
                </th>
                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Featured
                </th>
                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center text-sm text-zinc-500">
                    No products match your filters.
                  </td>
                </tr>
              ) : (
                filtered.map((product) => {
                  const category = getCategoryBySlug(product.categorySlug);
                  const onSale = product.oldPrice > product.price;
                  const thumb = product.images[0];

                  return (
                    <tr key={product.id} className="transition hover:bg-zinc-50/80">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <div className="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                            {thumb?.url ? (
                              // eslint-disable-next-line @next/next/no-img-element
                              <img
                                src={thumb.url}
                                alt={product.name}
                                className="h-full w-full object-cover"
                              />
                            ) : (
                              <div
                                className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${product.gradient}`}
                              >
                                <span className="text-base">{product.emoji}</span>
                              </div>
                            )}
                          </div>
                          <div className="min-w-0">
                            <Link
                              href={`/admin/products/${product.id}/edit`}
                              className="truncate font-medium text-zinc-900 hover:text-[#8b6914] hover:underline"
                            >
                              {product.name}
                            </Link>
                            <p className="truncate text-xs text-zinc-400">{product.slug}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <StatusBadge status={product.status} />
                      </td>
                      <td className="px-4 py-3">
                        <span className="text-xs text-zinc-600">
                          {category?.name ?? product.categorySlug}
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
                        {product.featured ? (
                          <StarOutlineIcon className="h-4 w-4 fill-[#c9a227] text-[#c9a227]" />
                        ) : (
                          <span className="text-xs text-zinc-300">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-1">
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
      </div>

      <DeleteProductModal
        product={deleteTarget}
        onConfirm={handleConfirmDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </>
  );
}
