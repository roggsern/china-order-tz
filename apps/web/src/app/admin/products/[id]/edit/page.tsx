"use client";

import Link from "next/link";
import { use } from "react";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { ProductForm } from "@/components/admin/ProductForm";
import { ChevronLeftIcon } from "@/components/home/icons";

interface EditProductPageProps {
  params: Promise<{ id: string }>;
}

export default function EditProductPage({ params }: EditProductPageProps) {
  const { id } = use(params);
  const productId = Number(id);
  const { getProduct, updateProduct } = useAdminProducts();
  const product = getProduct(productId);

  if (!product) {
    return (
      <div className="flex min-h-[50vh] flex-col items-center justify-center p-8 text-center">
        <span className="text-4xl">📦</span>
        <h1 className="mt-4 text-lg font-semibold text-zinc-900">Product not found</h1>
        <p className="mt-2 text-sm text-zinc-500">This product may have been deleted.</p>
        <Link
          href="/admin/products"
          className="mt-6 inline-flex items-center gap-1 text-sm font-medium text-[#8b6914] hover:underline"
        >
          <ChevronLeftIcon className="h-4 w-4" />
          Back to products
        </Link>
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <Link
        href="/admin/products"
        className="inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-[#8b6914]"
      >
        <ChevronLeftIcon className="h-4 w-4" />
        Products
      </Link>

      <div className="mt-4 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">{product.name}</h1>
          <p className="mt-0.5 text-sm text-zinc-500">Edit product details and settings.</p>
        </div>
      </div>

      <div className="mt-6">
        <ProductForm
          mode="edit"
          initialData={product}
          onSubmit={(data) => updateProduct(productId, data)}
        />
      </div>
    </div>
  );
}
