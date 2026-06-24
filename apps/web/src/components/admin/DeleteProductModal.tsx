"use client";

import type { Product } from "@/lib/types/catalog";
import { CloseIcon, TrashIcon } from "@/components/home/icons";

interface DeleteProductModalProps {
  product: Product | null;
  onConfirm: () => void;
  onCancel: () => void;
}

export function DeleteProductModal({ product, onConfirm, onCancel }: DeleteProductModalProps) {
  if (!product) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-zinc-900/50" onClick={onCancel} aria-hidden />
      <div
        className="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-product-title"
      >
        <button
          type="button"
          onClick={onCancel}
          className="absolute right-4 top-4 rounded-md p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600"
          aria-label="Close"
        >
          <CloseIcon className="h-5 w-5" />
        </button>

        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
          <TrashIcon className="h-5 w-5 text-red-600" />
        </div>

        <h2 id="delete-product-title" className="mt-4 text-base font-semibold text-zinc-900">
          Delete product?
        </h2>
        <p className="mt-2 text-sm text-zinc-500">
          <span className="font-medium text-zinc-700">{product.name}</span> will be permanently
          removed. This action cannot be undone.
        </p>

        <div className="mt-6 flex justify-end gap-3">
          <button
            type="button"
            onClick={onCancel}
            className="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
          >
            Delete product
          </button>
        </div>
      </div>
    </div>
  );
}
