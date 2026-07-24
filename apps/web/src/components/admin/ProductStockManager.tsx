"use client";

import { useCallback, useEffect, useState } from "react";
import {
  buildProductStockUpdatePayload,
  emptyProductStockFormState,
  mapProductStockToFormState,
  validateProductStockFormState,
  type ProductStockFormState,
} from "@/lib/admin/product-stock-sync";
import {
  AdminCatalogApiError,
  fetchAdminProductStock,
  updateAdminProductStock,
  type AdminProductStock,
} from "@/lib/api/admin-catalog";

type ProductStockManagerProps = {
  productId: string;
  onStockSaved?: (stock: AdminProductStock) => void;
};

export function ProductStockManager({ productId, onStockSaved }: ProductStockManagerProps) {
  const [form, setForm] = useState<ProductStockFormState>(emptyProductStockFormState);
  const [display, setDisplay] = useState<AdminProductStock | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const stock = await fetchAdminProductStock(productId);
      setDisplay(stock);
      setForm(mapProductStockToFormState(stock));
    } catch (err) {
      setDisplay(null);
      setForm(emptyProductStockFormState());
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to load product stock.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const handleSave = async () => {
    const validationErrors = validateProductStockFormState(form);
    setFieldErrors(validationErrors);
    if (Object.keys(validationErrors).length > 0) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      const saved = await updateAdminProductStock(
        productId,
        buildProductStockUpdatePayload(form),
      );
      setDisplay(saved);
      setForm(mapProductStockToFormState(saved));
      setSuccess("Stock saved.");
      onStockSaved?.(saved);
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to save product stock.",
      );
    } finally {
      setSaving(false);
    }
  };

  if (isLoading) {
    return <p className="text-sm text-zinc-500">Loading stock…</p>;
  }

  const reservedQuantity = display?.reservedQuantity ?? 0;
  const availableQuantity = Math.max(0, form.quantity - reservedQuantity);

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-sm font-semibold text-zinc-900">Simple Product Stock</h3>
        <p className="mt-1 text-xs text-zinc-500">
          Product-level catalog stock for simple products. Variant products manage inventory in the
          Variants tab.
        </p>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {success ? (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {success}
        </div>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-3">
        <div>
          <label className="admin-label" htmlFor="product-stock-quantity">
            Stock quantity (on hand)
          </label>
          <input
            id="product-stock-quantity"
            type="number"
            min={0}
            step={1}
            className="admin-input mt-1.5"
            value={form.quantity}
            onChange={(event) => {
              setFieldErrors({});
              setSuccess(null);
              setForm({ quantity: Number(event.target.value) || 0 });
            }}
          />
          {fieldErrors.quantity ? (
            <p className="mt-1 text-xs text-red-600">{fieldErrors.quantity}</p>
          ) : null}
        </div>

        <div>
          <p className="admin-label">Reserved</p>
          <p className="mt-1.5 text-sm font-medium text-zinc-900">{reservedQuantity}</p>
          <p className="mt-1 text-xs text-zinc-500">Held for open orders (read-only).</p>
        </div>

        <div>
          <p className="admin-label">Available</p>
          <p className="mt-1.5 text-sm font-medium text-zinc-900">{availableQuantity}</p>
          <p className="mt-1 text-xs text-zinc-500">On hand minus reserved.</p>
        </div>
      </div>

      {!display?.hasPolicy ? (
        <p className="text-xs text-amber-700">
          No inventory policy row yet. Saving will create product-level stock via the catalog
          inventory engine.
        </p>
      ) : null}

      <div>
        <button
          type="button"
          className="admin-btn-primary"
          disabled={saving}
          onClick={() => void handleSave()}
        >
          {saving ? "Saving…" : "Save stock"}
        </button>
      </div>
    </div>
  );
}
