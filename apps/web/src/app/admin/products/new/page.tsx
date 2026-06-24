"use client";

import Link from "next/link";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { ProductForm } from "@/components/admin/ProductForm";
import { ChevronLeftIcon } from "@/components/home/icons";

export default function NewProductPage() {
  const { addProduct } = useAdminProducts();

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <Link
        href="/admin/products"
        className="inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-[#8b6914]"
      >
        <ChevronLeftIcon className="h-4 w-4" />
        Products
      </Link>

      <div className="mt-4">
        <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">Add product</h1>
        <p className="mt-0.5 text-sm text-zinc-500">
          Create a new product for your catalog.
        </p>
      </div>

      <div className="mt-6">
        <ProductForm mode="create" onSubmit={addProduct} />
      </div>
    </div>
  );
}
