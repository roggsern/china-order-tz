"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { VariantInventoryManager } from "@/components/admin/VariantInventoryManager";
import { VariantPricingManager } from "@/components/admin/VariantPricingManager";
import {
  AdminCatalogApiError,
  createAdminProductVariant,
  deleteAdminProductVariant,
  fetchAdminProductVariants,
  generateAdminProductVariants,
  updateAdminProductVariant,
  type AdminProductVariant,
  type AdminVariantAttribute,
} from "@/lib/api/admin-catalog";

type ProductVariantsManagerProps = {
  productId: string;
};

type ManualForm = {
  name: string;
  sku: string;
  barcode: string;
  status: "active" | "inactive";
  isDefault: boolean;
  optionByAttribute: Record<string, string>;
};

const emptyForm = (): ManualForm => ({
  name: "",
  sku: "",
  barcode: "",
  status: "active",
  isDefault: false,
  optionByAttribute: {},
});

export function ProductVariantsManager({ productId }: ProductVariantsManagerProps) {
  const [variants, setVariants] = useState<AdminProductVariant[]>([]);
  const [attributes, setAttributes] = useState<AdminVariantAttribute[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<ManualForm>(emptyForm());
  const [generateSelected, setGenerateSelected] = useState<Record<string, string[]>>({});
  const [replaceExisting, setReplaceExisting] = useState(false);
  const [pricingVariantId, setPricingVariantId] = useState<string | null>(null);
  const [inventoryVariantId, setInventoryVariantId] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const payload = await fetchAdminProductVariants(productId);
      setVariants(payload.variants);
      setAttributes(payload.attributes);
      setGenerateSelected((current) => {
        const next: Record<string, string[]> = {};
        for (const attr of payload.attributes) {
          next[attr.catalogAttributeId] = current[attr.catalogAttributeId] ?? [];
        }
        return next;
      });
    } catch (err) {
      setVariants([]);
      setAttributes([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load variants.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const generatePreviewCount = useMemo(() => {
    const axes = attributes
      .map((attr) => generateSelected[attr.catalogAttributeId] ?? [])
      .filter((ids) => ids.length > 0);
    if (axes.length === 0) return 0;
    return axes.reduce((total, ids) => total * ids.length, 1);
  }, [attributes, generateSelected]);

  const startCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setSuccess(null);
    setError(null);
  };

  const startEdit = (variant: AdminProductVariant) => {
    const optionByAttribute: Record<string, string> = {};
    for (const row of variant.attributeValues) {
      if (row.optionId) {
        optionByAttribute[row.catalogAttributeId] = row.optionId;
      }
    }
    setEditingId(variant.id);
    setForm({
      name: variant.name ?? "",
      sku: variant.sku,
      barcode: variant.barcode ?? "",
      status: variant.status,
      isDefault: variant.isDefault,
      optionByAttribute,
    });
    setSuccess(null);
    setError(null);
  };

  const buildAttributeValues = () =>
    attributes
      .map((attr) => {
        const optionId = form.optionByAttribute[attr.catalogAttributeId];
        if (!optionId) return null;
        return {
          catalog_attribute_id: attr.catalogAttributeId,
          option_id: optionId,
        };
      })
      .filter((row): row is { catalog_attribute_id: string; option_id: string } => row !== null);

  const handleSave = async () => {
    setBusy(true);
    setError(null);
    setSuccess(null);
    const body = {
      name: form.name.trim() || null,
      sku: form.sku.trim() || null,
      barcode: form.barcode.trim() || null,
      status: form.status,
      is_default: form.isDefault,
      attribute_values: buildAttributeValues(),
    };

    try {
      if (editingId) {
        await updateAdminProductVariant(productId, editingId, body);
        setSuccess("Variant updated.");
      } else {
        await createAdminProductVariant(productId, body);
        setSuccess("Variant created.");
      }
      setEditingId(null);
      setForm(emptyForm());
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save variant.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (variant: AdminProductVariant) => {
    if (!window.confirm(`Delete variant “${variant.name || variant.sku}”?`)) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await deleteAdminProductVariant(productId, variant.id);
      setSuccess("Variant deleted.");
      if (editingId === variant.id) {
        setEditingId(null);
        setForm(emptyForm());
      }
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to delete variant.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleSetDefault = async (variant: AdminProductVariant) => {
    setBusy(true);
    setError(null);
    try {
      await updateAdminProductVariant(productId, variant.id, { is_default: true });
      setSuccess("Default variant updated.");
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to set default variant.",
      );
    } finally {
      setBusy(false);
    }
  };

  const toggleGenerateOption = (attributeId: string, optionId: string) => {
    setGenerateSelected((current) => {
      const existing = current[attributeId] ?? [];
      const next = existing.includes(optionId)
        ? existing.filter((id) => id !== optionId)
        : [...existing, optionId];
      return { ...current, [attributeId]: next };
    });
  };

  const handleGenerate = async () => {
    const payload = attributes
      .map((attr) => ({
        catalog_attribute_id: attr.catalogAttributeId,
        option_ids: generateSelected[attr.catalogAttributeId] ?? [],
      }))
      .filter((row) => row.option_ids.length > 0);

    if (payload.length === 0) {
      setError("Select at least one attribute option to generate variants.");
      return;
    }

    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      const result = await generateAdminProductVariants(productId, {
        attributes: payload,
        replace_existing: replaceExisting,
      });
      setVariants(result.variants);
      setAttributes(result.attributes);
      setSuccess(
        `Generated ${result.createdCount ?? 0} new variant${
          (result.createdCount ?? 0) === 1 ? "" : "s"
        }.`,
      );
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to generate variants.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (isLoading) {
    return <p className="text-sm text-zinc-500">Loading variants…</p>;
  }

  return (
    <div className="space-y-6">
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

      <p className="text-xs text-zinc-500">
        Variants are purchasable units. Use Pricing and Inventory for the Pricing Engine and
        Inventory Engine.
      </p>

      <div className="overflow-x-auto rounded-lg border border-zinc-200">
        <table className="min-w-full text-left text-sm">
            <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
            <tr>
              <th className="px-3 py-2 font-medium">Name</th>
              <th className="px-3 py-2 font-medium">SKU</th>
              <th className="px-3 py-2 font-medium">Attributes</th>
              <th className="px-3 py-2 font-medium">Pricing</th>
              <th className="px-3 py-2 font-medium">Inventory</th>
              <th className="px-3 py-2 font-medium">Status</th>
              <th className="px-3 py-2 font-medium">Default</th>
              <th className="px-3 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {variants.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-3 py-6 text-center text-zinc-500">
                  No variants yet. Create manually or generate combinations below.
                </td>
              </tr>
            ) : (
              variants.map((variant) => (
                <tr key={variant.id} className="border-t border-zinc-100">
                  <td className="px-3 py-2 font-medium text-zinc-900">
                    {variant.name || "—"}
                  </td>
                  <td className="px-3 py-2 font-mono text-xs text-zinc-700">{variant.sku}</td>
                  <td className="px-3 py-2 text-zinc-600">
                    {variant.attributeValues.length > 0
                      ? variant.attributeValues
                          .map((row) => row.display || row.optionValue || row.valueText)
                          .filter(Boolean)
                          .join(" / ")
                      : "—"}
                  </td>
                  <td className="px-3 py-2 text-xs text-zinc-600">
                    {variant.pricesCount} price{variant.pricesCount === 1 ? "" : "s"}
                  </td>
                  <td className="px-3 py-2 text-xs text-zinc-600">
                    {variant.inventoriesCount} warehouse
                    {variant.inventoriesCount === 1 ? "" : "s"}
                  </td>
                  <td className="px-3 py-2">
                    <span
                      className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${
                        variant.status === "active"
                          ? "bg-emerald-50 text-emerald-700"
                          : "bg-zinc-100 text-zinc-600"
                      }`}
                    >
                      {variant.status}
                    </span>
                  </td>
                  <td className="px-3 py-2">
                    {variant.isDefault ? (
                      <span className="text-xs font-medium text-zinc-800">Default</span>
                    ) : (
                      <button
                        type="button"
                        className="text-xs text-zinc-500 hover:text-zinc-800"
                        disabled={busy}
                        onClick={() => void handleSetDefault(variant)}
                      >
                        Set default
                      </button>
                    )}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-2">
                      <button
                        type="button"
                        className="text-xs font-medium text-zinc-700 hover:underline"
                        disabled={busy}
                        onClick={() => startEdit(variant)}
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        className="text-xs font-medium text-zinc-700 hover:underline"
                        disabled={busy}
                        onClick={() => {
                          setInventoryVariantId(null);
                          setPricingVariantId(variant.id);
                        }}
                      >
                        Pricing
                      </button>
                      <button
                        type="button"
                        className="text-xs font-medium text-zinc-700 hover:underline"
                        disabled={busy}
                        onClick={() => {
                          setPricingVariantId(null);
                          setInventoryVariantId(variant.id);
                        }}
                      >
                        Inventory
                      </button>
                      <button
                        type="button"
                        className="text-xs font-medium text-red-600 hover:underline"
                        disabled={busy}
                        onClick={() => void handleDelete(variant)}
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {pricingVariantId ? (
        <VariantPricingManager
          variantId={pricingVariantId}
          variantLabel={
            variants.find((variant) => variant.id === pricingVariantId)?.name ||
            variants.find((variant) => variant.id === pricingVariantId)?.sku ||
            "Variant"
          }
          onClose={() => setPricingVariantId(null)}
        />
      ) : null}

      {inventoryVariantId ? (
        <VariantInventoryManager
          variantId={inventoryVariantId}
          variantLabel={
            variants.find((variant) => variant.id === inventoryVariantId)?.name ||
            variants.find((variant) => variant.id === inventoryVariantId)?.sku ||
            "Variant"
          }
          onClose={() => setInventoryVariantId(null)}
        />
      ) : null}

      <div className="rounded-lg border border-zinc-200 p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-sm font-semibold text-zinc-900">
            {editingId ? "Edit variant" : "Create variant"}
          </h3>
          {editingId ? (
            <button
              type="button"
              className="text-xs text-zinc-500 hover:text-zinc-800"
              onClick={startCreate}
            >
              Cancel edit
            </button>
          ) : null}
        </div>

        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          <div>
            <label className="admin-label" htmlFor="variant-name">
              Name
            </label>
            <input
              id="variant-name"
              className="admin-input mt-1"
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
              placeholder="Black 256GB"
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="variant-sku">
              SKU
            </label>
            <input
              id="variant-sku"
              className="admin-input mt-1"
              value={form.sku}
              onChange={(event) => setForm({ ...form, sku: event.target.value })}
              placeholder="Auto-generated if empty"
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="variant-barcode">
              Barcode
            </label>
            <input
              id="variant-barcode"
              className="admin-input mt-1"
              value={form.barcode}
              onChange={(event) => setForm({ ...form, barcode: event.target.value })}
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="variant-status">
              Status
            </label>
            <select
              id="variant-status"
              className="admin-input mt-1"
              value={form.status}
              onChange={(event) =>
                setForm({
                  ...form,
                  status: event.target.value === "inactive" ? "inactive" : "active",
                })
              }
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        {attributes.length > 0 ? (
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            {attributes.map((attr) => (
              <div key={attr.catalogAttributeId}>
                <label className="admin-label" htmlFor={`variant-attr-${attr.catalogAttributeId}`}>
                  {attr.name}
                </label>
                <select
                  id={`variant-attr-${attr.catalogAttributeId}`}
                  className="admin-input mt-1"
                  value={form.optionByAttribute[attr.catalogAttributeId] ?? ""}
                  onChange={(event) =>
                    setForm({
                      ...form,
                      optionByAttribute: {
                        ...form.optionByAttribute,
                        [attr.catalogAttributeId]: event.target.value,
                      },
                    })
                  }
                >
                  <option value="">Select…</option>
                  {attr.options.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.value}
                    </option>
                  ))}
                </select>
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-3 text-xs text-zinc-500">
            No select attributes on this product type. You can still create a default SKU
            without attribute combinations.
          </p>
        )}

        <label className="mt-3 flex items-center gap-2 text-sm text-zinc-700">
          <input
            type="checkbox"
            checked={form.isDefault}
            onChange={(event) => setForm({ ...form, isDefault: event.target.checked })}
          />
          Default variant
        </label>

        <div className="mt-4 flex gap-2">
          <button
            type="button"
            className="admin-btn-primary"
            disabled={busy}
            onClick={() => void handleSave()}
          >
            {busy ? "Saving…" : editingId ? "Update variant" : "Create variant"}
          </button>
        </div>
      </div>

      <div className="rounded-lg border border-zinc-200 p-4">
        <h3 className="text-sm font-semibold text-zinc-900">Generate combinations</h3>
        <p className="mt-1 text-xs text-zinc-500">
          Select options per attribute. Example: Color × Storage → Black 128GB, Black 256GB,
          White 128GB, White 256GB.
        </p>

        {attributes.length === 0 ? (
          <p className="mt-3 text-sm text-zinc-500">
            Assign select attributes to the product type to enable generation.
          </p>
        ) : (
          <div className="mt-3 space-y-3">
            {attributes.map((attr) => (
              <div key={attr.catalogAttributeId}>
                <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                  {attr.name}
                </p>
                <div className="mt-1 flex flex-wrap gap-3">
                  {attr.options.map((option) => {
                    const checked = (
                      generateSelected[attr.catalogAttributeId] ?? []
                    ).includes(option.id);
                    return (
                      <label
                        key={option.id}
                        className="flex items-center gap-2 text-sm text-zinc-700"
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() =>
                            toggleGenerateOption(attr.catalogAttributeId, option.id)
                          }
                        />
                        {option.value}
                      </label>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        )}

        <label className="mt-3 flex items-center gap-2 text-sm text-zinc-700">
          <input
            type="checkbox"
            checked={replaceExisting}
            onChange={(event) => setReplaceExisting(event.target.checked)}
          />
          Replace existing variants
        </label>

        <div className="mt-4 flex flex-wrap items-center gap-3">
          <button
            type="button"
            className="admin-btn-primary"
            disabled={busy || generatePreviewCount === 0}
            onClick={() => void handleGenerate()}
          >
            {busy ? "Generating…" : `Generate (${generatePreviewCount})`}
          </button>
          <span className="text-xs text-zinc-500">
            Existing matching combinations are skipped unless replace is enabled.
          </span>
        </div>
      </div>
    </div>
  );
}
