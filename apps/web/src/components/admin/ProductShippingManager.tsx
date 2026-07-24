"use client";

import { useCallback, useEffect, useState } from "react";
import {
  buildProductShippingSyncPayload,
  emptyProductShippingFormState,
  mapShippingOptionsToFormState,
  validateProductShippingFormState,
  type ProductShippingFormState,
} from "@/lib/admin/product-shipping-sync";
import {
  AdminCatalogApiError,
  fetchAdminProductShippingOptions,
  syncAdminProductShippingOptions,
} from "@/lib/api/admin-catalog";

type ProductShippingManagerProps = {
  productId: string;
  onSaved?: () => void;
};

export function ProductShippingManager({ productId, onSaved }: ProductShippingManagerProps) {
  const [form, setForm] = useState<ProductShippingFormState>(emptyProductShippingFormState);
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
      const options = await fetchAdminProductShippingOptions(productId);
      setForm(
        options.length > 0
          ? mapShippingOptionsToFormState(options)
          : emptyProductShippingFormState(),
      );
    } catch (err) {
      setForm(emptyProductShippingFormState());
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load shipping options.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const handleSave = async () => {
    const validationErrors = validateProductShippingFormState(form);
    setFieldErrors(validationErrors);
    if (Object.keys(validationErrors).length > 0) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      const saved = await syncAdminProductShippingOptions(
        productId,
        buildProductShippingSyncPayload(form),
      );
      setForm(mapShippingOptionsToFormState(saved));
      setSuccess("Shipping options saved.");
      onSaved?.();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save shipping options.",
      );
    } finally {
      setSaving(false);
    }
  };

  const updateMode = (
    mode: "air" | "sea",
    patch: Partial<ProductShippingFormState["air"]>,
  ) => {
    setFieldErrors({});
    setSuccess(null);
    setForm((current) => ({
      ...current,
      [mode]: {
        ...current[mode],
        ...patch,
      },
    }));
  };

  if (isLoading) {
    return <p className="text-sm text-zinc-500">Loading shipping options…</p>;
  }

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-sm font-semibold text-zinc-900">Manage Shipping Options</h3>
        <p className="mt-1 text-xs text-zinc-500">
          Enter prices manually. The system never calculates shipping costs. Leave a mode
          unavailable to hide it from customers. Customer Agent ignores these prices.
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

      {fieldErrors.shipping ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {fieldErrors.shipping}
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <ShippingModeCard
          title="Air Freight"
          mode={form.air}
          priceError={fieldErrors.airPrice}
          onChange={(patch) => updateMode("air", patch)}
        />
        <ShippingModeCard
          title="Sea Freight"
          mode={form.sea}
          priceError={fieldErrors.seaPrice}
          onChange={(patch) => updateMode("sea", patch)}
        />
      </div>

      <div>
        <button
          type="button"
          className="admin-btn-primary"
          disabled={saving}
          onClick={() => void handleSave()}
        >
          {saving ? "Saving…" : "Save shipping options"}
        </button>
      </div>
    </div>
  );
}

function ShippingModeCard({
  title,
  mode,
  priceError,
  onChange,
}: {
  title: string;
  mode: ProductShippingFormState["air"];
  priceError?: string;
  onChange: (patch: Partial<ProductShippingFormState["air"]>) => void;
}) {
  return (
    <div className="rounded-xl border border-zinc-200 p-4">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm font-semibold text-zinc-900">{title}</p>
        <label className="flex items-center gap-2 text-xs text-zinc-600">
          <input
            type="checkbox"
            checked={mode.available}
            onChange={(event) => onChange({ available: event.target.checked })}
          />
          Available
        </label>
      </div>
      <div className="mt-3">
        <label className="admin-label">Price (TZS)</label>
        <input
          type="number"
          min={0}
          disabled={!mode.available}
          value={mode.available ? mode.price || "" : ""}
          onChange={(event) => onChange({ price: Number(event.target.value) || 0 })}
          className="admin-input mt-1.5"
        />
        {priceError ? <p className="mt-1 text-xs text-red-600">{priceError}</p> : null}
      </div>
      <div className="mt-3">
        <label className="admin-label">Notes</label>
        <input
          type="text"
          disabled={!mode.available}
          value={mode.notes}
          onChange={(event) => onChange({ notes: event.target.value })}
          className="admin-input mt-1.5"
          placeholder="Optional"
        />
      </div>
    </div>
  );
}
