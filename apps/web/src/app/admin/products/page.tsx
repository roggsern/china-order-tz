"use client";

import Link from "next/link";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { ProductTable } from "@/components/admin/ProductTable";
import { PlusIcon } from "@/components/home/icons";

export default function AdminProductsPage() {
  const { products, deleteProduct, deleteProducts, isHydrated } = useAdminProducts();

  const activeCount = products.filter((product) => product.status === "active").length;
  const featuredCount = products.filter((product) => product.featured).length;
  const lowStockCount = products.filter((product) => product.stock > 0 && product.stock <= 10).length;

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">Products</h1>
          <p className="mt-0.5 text-sm text-zinc-500">
            Manage your catalog — add, edit, or remove products.
          </p>
        </div>
        <Link
          href="/admin/products/new"
          className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#c9a227] px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
        >
          <PlusIcon className="h-4 w-4" />
          Add product
        </Link>
      </div>

      <div className="mt-6 grid gap-4 sm:grid-cols-3">
        <div className="admin-card p-4">
          <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Total</p>
          <p className="mt-1 text-2xl font-semibold text-zinc-900">{products.length}</p>
        </div>
        <div className="admin-card p-4">
          <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Active</p>
          <p className="mt-1 text-2xl font-semibold text-emerald-600">{activeCount}</p>
        </div>
        <div className="admin-card p-4">
          <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">
            Featured · Low stock
          </p>
          <p className="mt-1 text-2xl font-semibold text-zinc-900">
            {featuredCount}
            <span className="ml-2 text-base font-normal text-zinc-400">·</span>
            <span className="ml-2 text-lg font-semibold text-amber-600">{lowStockCount}</span>
          </p>
        </div>
      </div>

      <div className="mt-6">
        <ProductTable
          products={products}
          isHydrated={isHydrated}
          onDelete={deleteProduct}
          onBulkDelete={deleteProducts}
        />
      </div>
    </div>
  );
}
