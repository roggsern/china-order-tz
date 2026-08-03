"use client";

import { useCallback, useEffect, useState } from "react";
import {
  buildProductCommercialStockUpdatePayload,
  buildVariantCommercialStockUpdatePayload,
  emptyProductCommercialStockFormState,
  validateCommercialAvailableQuantity,
  type AdminProductCommercialStock,
  type ProductCommercialStockFormState,
} from "@/lib/admin/product-commercial-stock-sync";
import {
  AdminCatalogApiError,
  fetchAdminProductCommercialStock,
  updateAdminProductCommercialStock,
  updateAdminVariantCommercialStock,
} from "@/lib/api/admin-catalog";

type ProductCommercialAvailabilityManagerProps = {
  productId: string;
  onSaved?: () => void;
};

export function ProductCommercialAvailabilityManager({
  productId,
  onSaved,
}: ProductCommercialAvailabilityManagerProps) {
  const [data, setData] = useState<AdminProductCommercialStock | null>(null);
  const [simpleForm, setSimpleForm] = useState<ProductCommercialStockFormState>(
    emptyProductCommercialStockFormState(),
  );
  const [variantDrafts, setVariantDrafts] = useState<Record<string, number>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [savingSimple, setSavingSimple] = useState(false);
  const [savingVariantId, setSavingVariantId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const next = await fetchAdminProductCommercialStock(productId);
      setData(next);

      if (next.path === "simple" && next.simple) {
        setSimpleForm({ availableQuantity: next.simple.availableQuantity });
      } else {
        setVariantDrafts(
          Object.fromEntries(
            next.variants.map((variant) => [variant.variantId, variant.availableQuantity]),
          ),
        );
      }
    } catch (err) {
      setData(null);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load commercial availability.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const handleSaveSimple = async () => {
    const validationError = validateCommercialAvailableQuantity(simpleForm.availableQuantity);
    setFieldError(validationError);
    if (validationError) {
      return;
    }

    setSavingSimple(true);
    setError(null);
    setSuccess(null);

    try {
      await updateAdminProductCommercialStock(
        productId,
        buildProductCommercialStockUpdatePayload(simpleForm),
      );
      setSuccess("Commercial availability saved.");
      onSaved?.();
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save commercial availability.",
      );
    } finally {
      setSavingSimple(false);
    }
  };

  const handleSaveVariant = async (variantId: string) => {
    const quantity = variantDrafts[variantId] ?? 0;
    const validationError = validateCommercialAvailableQuantity(quantity);
    if (validationError) {
      setError(validationError);
      return;
    }

    setSavingVariantId(variantId);
    setError(null);
    setSuccess(null);

    try {
      await updateAdminVariantCommercialStock(
        variantId,
        buildVariantCommercialStockUpdatePayload(quantity),
      );
      setSuccess("Variant commercial availability saved.");
      onSaved?.();
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save variant commercial availability.",
      );
    } finally {
      setSavingVariantId(null);
    }
  };

  if (isLoading) {
    return <p className="text-sm text-zinc-500">Loading commercial availability…</p>;
  }

  if (error && !data) {
    return <p className="text-sm text-red-600">{error}</p>;
  }

  return (
    <div className="space-y-4">
      <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
        <p className="font-medium">Commercial Availability</p>
        <p className="mt-1 text-xs text-sky-900">
          Customer-facing availability for China Import products. This is separate from TZ
          warehouse inventory and is reduced after successful customer payment.
        </p>
        {data?.path === "simple" ? (
          <p className="mt-2 text-xs text-sky-900">
            No variants. Stock is managed at product level.
          </p>
        ) : (
          <p className="mt-2 text-xs text-sky-900">
            Stock is managed per variant.
          </p>
        )}
      </div>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      {data?.path === "simple" && data.simple ? (
        <div className="max-w-md space-y-3">
          <label className="admin-label" htmlFor="commercial-available-quantity">
            Available quantity
          </label>
          <input
            id="commercial-available-quantity"
            type="number"
            min={0}
            step={1}
            className="admin-input mt-1.5"
            value={simpleForm.availableQuantity}
            onChange={(event) => {
              setFieldError(null);
              setSimpleForm({
                availableQuantity: Number(event.target.value) || 0,
              });
            }}
          />
          {fieldError ? <p className="text-xs text-red-600">{fieldError}</p> : null}
          <div className="text-xs text-zinc-500">
            Reserved: {data.simple.reservedQuantity} · Ordered: {data.simple.orderedQuantity}
          </div>
          <button
            type="button"
            className="admin-btn-primary"
            disabled={savingSimple}
            onClick={() => void handleSaveSimple()}
          >
            {savingSimple ? "Saving…" : "Save commercial availability"}
          </button>
        </div>
      ) : null}

      {data?.path === "variant" ? (
        <div className="overflow-x-auto rounded-lg border border-zinc-200">
          <table className="min-w-full divide-y divide-zinc-200 text-sm">
            <thead className="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
              <tr>
                <th className="px-3 py-2 font-medium">Variant</th>
                <th className="px-3 py-2 font-medium">SKU</th>
                <th className="px-3 py-2 font-medium">Available quantity</th>
                <th className="px-3 py-2 font-medium">Reserved</th>
                <th className="px-3 py-2 font-medium">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 bg-white">
              {data.variants.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-3 py-4 text-zinc-500">
                    No variants yet. Create variants first, then set commercial availability per
                    variant.
                  </td>
                </tr>
              ) : (
                data.variants.map((variant) => (
                  <tr key={variant.variantId}>
                    <td className="px-3 py-2">
                      <div className="font-medium text-zinc-900">{variant.name}</div>
                      {!variant.isActive ? (
                        <div className="text-xs text-amber-700">Inactive</div>
                      ) : null}
                    </td>
                    <td className="px-3 py-2 text-zinc-600">{variant.sku || "—"}</td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        min={0}
                        step={1}
                        className="admin-input w-28"
                        value={variantDrafts[variant.variantId] ?? 0}
                        onChange={(event) =>
                          setVariantDrafts((current) => ({
                            ...current,
                            [variant.variantId]: Number(event.target.value) || 0,
                          }))
                        }
                      />
                    </td>
                    <td className="px-3 py-2 text-zinc-600">{variant.reservedQuantity}</td>
                    <td className="px-3 py-2">
                      <button
                        type="button"
                        className="admin-btn-secondary text-xs"
                        disabled={savingVariantId === variant.variantId}
                        onClick={() => void handleSaveVariant(variant.variantId)}
                      >
                        {savingVariantId === variant.variantId ? "Saving…" : "Save"}
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
