"use client";

import Link from "next/link";
import { use, useCallback, useState } from "react";
import { useRouter } from "next/navigation";
import type { ProductFormData } from "@/lib/types/catalog";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { DeleteProductModal } from "@/components/admin/DeleteProductModal";
import { ProductForm } from "@/components/admin/ProductForm";
import { ChevronLeftIcon } from "@/components/home/icons";

interface EditProductPageProps {
  params: Promise<{ id: string }>;
}

export default function EditProductPage({ params }: EditProductPageProps) {
  const router = useRouter();
  const { id } = use(params);
  const productId = Number(id);
  const { getProduct, updateProduct, deleteProduct, isHydrated } = useAdminProducts();
  const product = getProduct(productId);
  const isEditMode = true;
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  const handleSaveProduct = useCallback(
    (data: ProductFormData) => {
      updateProduct(productId, data);
    },
    [productId, updateProduct],
  );

  const handleDeleteProduct = useCallback(() => {
    setIsDeleteModalOpen(true);
  }, []);

  const handleConfirmDeleteProduct = useCallback(() => {
    deleteProduct(productId);
    setIsDeleteModalOpen(false);
    router.push("/admin/products");
  }, [deleteProduct, productId, router]);

  if (!isHydrated) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center p-8 text-sm text-zinc-500">
        Loading product…
      </div>
    );
  }

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
    <>
      <div className="p-4 sm:p-6 lg:p-8">
        <Link
          href="/admin/products"
          className="inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-[#8b6914]"
        >
          <ChevronLeftIcon className="h-4 w-4" />
          Products
        </Link>

        <div className="mt-4">
          <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">{product.name}</h1>
          <p className="mt-0.5 text-sm text-zinc-500">Edit product details and settings.</p>
        </div>

        <div className="mt-6">
          <ProductForm
            isEditMode={isEditMode}
            initialData={product}
            onSubmit={handleSaveProduct}
            onDeleteProduct={handleDeleteProduct}
          />
        </div>
      </div>

      {isDeleteModalOpen && (
        <DeleteProductModal
          product={product}
          onConfirm={handleConfirmDeleteProduct}
          onCancel={() => setIsDeleteModalOpen(false)}
        />
      )}
    </>
  );
}
