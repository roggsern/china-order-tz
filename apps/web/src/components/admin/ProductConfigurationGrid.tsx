"use client";

import { useEffect, useMemo, useState } from "react";
import type {
  ProductConfigurationDraft,
  ProductFormSchema,
  ProductFormSchemaAttribute,
  ProductPriceTierDraft,
} from "@/lib/types/catalog";
import {
  AdminCatalogApiError,
  fetchProductFormSchema,
  generateAdminConfigurations,
} from "@/lib/api/admin-catalog";

interface ProductConfigurationGridProps {
  categoryId: string;
  baseSku: string;
  basePrice: number;
  configurations: ProductConfigurationDraft[];
  onChange: (configurations: ProductConfigurationDraft[]) => void;
  allowsSimpleStock?: boolean;
  simpleStock?: number;
  onSimpleStockChange?: (stock: number) => void;
}

function buildValueLabels(schema: ProductFormSchema | null): Record<string, string> {
  if (!schema) return {};

  const labels: Record<string, string> = {};
  for (const attribute of schema.attributes) {
    for (const value of attribute.values) {
      labels[value.id] = value.value;
    }
  }
  return labels;
}

function defaultSelectedValues(
  attributes: ProductFormSchemaAttribute[],
): Record<string, string[]> {
  const selected: Record<string, string[]> = {};
  for (const attribute of attributes) {
    if (!attribute.participates_in_configuration) continue;
    selected[attribute.id] = attribute.values.map((value) => value.id);
  }
  return selected;
}

export function ProductConfigurationGrid({
  categoryId,
  baseSku,
  basePrice,
  configurations,
  onChange,
  allowsSimpleStock = true,
  simpleStock = 0,
  onSimpleStockChange,
}: ProductConfigurationGridProps) {
  const [schema, setSchema] = useState<ProductFormSchema | null>(null);
  const [schemaLoading, setSchemaLoading] = useState(false);
  const [schemaError, setSchemaError] = useState<string | null>(null);
  const [selectedValues, setSelectedValues] = useState<Record<string, string[]>>({});
  const [generating, setGenerating] = useState(false);
  const [generateError, setGenerateError] = useState<string | null>(null);

  useEffect(() => {
    if (!categoryId) {
      setSchema(null);
      setSelectedValues({});
      setSchemaError(null);
      return;
    }

    let cancelled = false;

    async function loadSchema() {
      setSchemaLoading(true);
      setSchemaError(null);

      try {
        const nextSchema = await fetchProductFormSchema(categoryId);
        if (cancelled) return;

        setSchema(nextSchema);
        setSelectedValues(
          defaultSelectedValues(
            nextSchema.attributes.filter((attr) => attr.participates_in_configuration),
          ),
        );
      } catch (err) {
        if (!cancelled) {
          setSchema(null);
          setSelectedValues({});
          setSchemaError(
            err instanceof AdminCatalogApiError
              ? err.message
              : "Unable to load product type schema.",
          );
        }
      } finally {
        if (!cancelled) {
          setSchemaLoading(false);
        }
      }
    }

    void loadSchema();

    return () => {
      cancelled = true;
    };
  }, [categoryId]);

  const configAttributes = useMemo(
    () =>
      (schema?.attributes ?? []).filter((attribute) => attribute.participates_in_configuration),
    [schema],
  );

  const hasConfigurationsCapability = Boolean(schema?.capabilities.has_configurations);
  const allowsPriceOverride = Boolean(schema?.capabilities.allows_price_override);
  const allowsMoqPricing = Boolean(schema?.capabilities.allows_moq_pricing);
  const productTypeName = schema?.product_type?.name ?? null;

  const toggleValue = (attributeId: string, valueId: string) => {
    setSelectedValues((prev) => {
      const current = prev[attributeId] ?? [];
      const next = current.includes(valueId)
        ? current.filter((id) => id !== valueId)
        : [...current, valueId];
      return { ...prev, [attributeId]: next };
    });
  };

  const handleGenerate = async () => {
    if (!schema?.product_type?.id) return;

    setGenerating(true);
    setGenerateError(null);

    try {
      const rows = await generateAdminConfigurations({
        productTypeId: schema.product_type.id,
        selectedValues,
        baseSku: baseSku.trim() || "SKU",
        defaultPrice: allowsPriceOverride ? basePrice : null,
        valueLabels: buildValueLabels(schema),
      });

      onChange(rows);
    } catch (err) {
      setGenerateError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to generate configurations.",
      );
    } finally {
      setGenerating(false);
    }
  };

  const updateRow = (
    index: number,
    patch: Partial<ProductConfigurationDraft>,
  ) => {
    onChange(
      configurations.map((row, rowIndex) =>
        rowIndex === index ? { ...row, ...patch } : row,
      ),
    );
  };

  const removeRow = (index: number) => {
    onChange(configurations.filter((_, rowIndex) => rowIndex !== index));
  };

  if (!categoryId) {
    return (
      <section className="admin-card p-5">
        <h2 className="text-sm font-semibold text-zinc-900">Configurations</h2>
        <p className="mt-1 text-xs text-zinc-500">
          Select a category to load its Product Type schema.
        </p>
      </section>
    );
  }

  return (
    <section className="admin-card p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-zinc-900">
            Product configurations
          </h2>
          <p className="mt-1 text-xs text-zinc-500">
            Driven by Product Type metadata. Only valid combinations from the
            Attribute Dependency Engine are generated.
          </p>
        </div>
        {productTypeName ? (
          <span className="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">
            Type: {productTypeName}
          </span>
        ) : null}
      </div>

      {schemaLoading ? (
        <p className="mt-4 text-sm text-zinc-500">Loading product form schema…</p>
      ) : null}

      {schemaError ? (
        <p className="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
          {schemaError}
        </p>
      ) : null}

      {!schemaLoading && schema && !schema.product_type ? (
        <p className="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
          This category has no Product Type assigned. Assign a type in Admin Categories
          before building configurations.
        </p>
      ) : null}

      {!schemaLoading && schema?.product_type && !hasConfigurationsCapability ? (
        <div className="mt-4">
          <p className="text-sm text-zinc-600">
            This Product Type does not use configurations. Use simple stock below.
          </p>
          {allowsSimpleStock && onSimpleStockChange ? (
            <div className="mt-3 max-w-xs">
              <label className="admin-label" htmlFor="simple-stock">
                Stock quantity *
              </label>
              <input
                id="simple-stock"
                type="number"
                min={0}
                value={simpleStock}
                onChange={(event) => onSimpleStockChange(Number(event.target.value))}
                className="admin-input mt-1.5"
              />
            </div>
          ) : null}
        </div>
      ) : null}

      {!schemaLoading && hasConfigurationsCapability && configAttributes.length > 0 ? (
        <div className="mt-5 space-y-5">
          {configAttributes.map((attribute) => (
            <div key={attribute.id}>
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-medium text-zinc-800">{attribute.name}</p>
                {attribute.is_required ? (
                  <span className="text-[11px] font-medium uppercase tracking-wide text-zinc-500">
                    Required
                  </span>
                ) : null}
                {attribute.unit ? (
                  <span className="text-[11px] text-zinc-400">({attribute.unit})</span>
                ) : null}
              </div>
              <div className="mt-2 flex flex-wrap gap-2">
                {attribute.values.map((value) => {
                  const checked = (selectedValues[attribute.id] ?? []).includes(value.id);
                  return (
                    <label
                      key={value.id}
                      className={`inline-flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-medium transition ${
                        checked
                          ? "border-[#c9a227] bg-[#c9a227]/10 text-zinc-900"
                          : "border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300"
                      }`}
                    >
                      <input
                        type="checkbox"
                        className="sr-only"
                        checked={checked}
                        onChange={() => toggleValue(attribute.id, value.id)}
                      />
                      {attribute.type === "color" && value.color_code ? (
                        <span
                          className="h-3 w-3 rounded-full border border-zinc-300"
                          style={{ backgroundColor: value.color_code }}
                          aria-hidden
                        />
                      ) : null}
                      {value.value}
                    </label>
                  );
                })}
              </div>
            </div>
          ))}

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={() => void handleGenerate()}
              disabled={generating || !baseSku.trim()}
              className="rounded-md bg-zinc-900 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {generating ? "Generating…" : "Generate configurations"}
            </button>
            {!baseSku.trim() ? (
              <p className="text-xs text-amber-700">Enter a base SKU first (used for auto SKUs).</p>
            ) : (
              <p className="text-xs text-zinc-500">
                {configurations.length} configuration
                {configurations.length === 1 ? "" : "s"} ready
              </p>
            )}
          </div>

          {generateError ? (
            <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
              {generateError}
            </p>
          ) : null}

          {configurations.length > 0 ? (
            <div className="overflow-x-auto rounded-lg border border-zinc-200">
              <table className="min-w-full divide-y divide-zinc-200 text-left text-sm">
                <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                  <tr>
                    <th className="px-3 py-2.5 font-semibold">Configuration</th>
                    <th className="px-3 py-2.5 font-semibold">SKU</th>
                    <th className="px-3 py-2.5 font-semibold">Stock</th>
                    {allowsPriceOverride ? (
                      <th className="px-3 py-2.5 font-semibold">Price override</th>
                    ) : null}
                    <th className="px-3 py-2.5 font-semibold">Barcode</th>
                    {allowsMoqPricing ? (
                      <th className="px-3 py-2.5 font-semibold">Wholesale tiers</th>
                    ) : null}
                    <th className="px-3 py-2.5 font-semibold">
                      <span className="sr-only">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-zinc-100 bg-white">
                  {configurations.map((row, index) => (
                    <tr key={`${row.sku}-${row.attributeValueIds.join("-")}-${index}`}>
                      <td className="px-3 py-2 align-middle text-zinc-800">{row.label}</td>
                      <td className="px-3 py-2 align-middle">
                        <input
                          type="text"
                          value={row.sku}
                          onChange={(event) => updateRow(index, { sku: event.target.value })}
                          className="admin-input font-mono text-xs"
                        />
                      </td>
                      <td className="px-3 py-2 align-middle">
                        <input
                          type="number"
                          min={0}
                          value={row.stock}
                          onChange={(event) =>
                            updateRow(index, { stock: Number(event.target.value) })
                          }
                          className="admin-input w-24"
                        />
                      </td>
                      {allowsPriceOverride ? (
                        <td className="px-3 py-2 align-middle">
                          <input
                            type="number"
                            min={0}
                            placeholder={String(basePrice || "")}
                            value={row.price ?? ""}
                            onChange={(event) =>
                              updateRow(index, {
                                price: event.target.value
                                  ? Number(event.target.value)
                                  : null,
                              })
                            }
                            className="admin-input w-32"
                          />
                        </td>
                      ) : null}
                      <td className="px-3 py-2 align-middle">
                        <input
                          type="text"
                          value={row.barcode}
                          placeholder="Optional"
                          onChange={(event) =>
                            updateRow(index, { barcode: event.target.value })
                          }
                          className="admin-input"
                        />
                      </td>
                      {allowsMoqPricing ? (
                        <td className="px-3 py-2 align-top">
                          <div className="min-w-[14rem] space-y-2">
                            {(row.priceTiers ?? []).map((tier, tierIndex) => (
                              <div key={tierIndex} className="flex gap-1">
                                <input
                                  type="number"
                                  min={1}
                                  title="Min quantity"
                                  value={tier.minQuantity}
                                  onChange={(event) => {
                                    const next: ProductPriceTierDraft[] = [
                                      ...(row.priceTiers ?? []),
                                    ];
                                    next[tierIndex] = {
                                      ...tier,
                                      minQuantity: Number(event.target.value) || 1,
                                    };
                                    updateRow(index, { priceTiers: next });
                                  }}
                                  className="admin-input w-16 text-xs"
                                  placeholder="Qty"
                                />
                                <input
                                  type="number"
                                  min={0}
                                  title="Unit price"
                                  value={tier.unitPrice ?? ""}
                                  onChange={(event) => {
                                    const next: ProductPriceTierDraft[] = [
                                      ...(row.priceTiers ?? []),
                                    ];
                                    next[tierIndex] = {
                                      ...tier,
                                      tierType: "fixed_unit",
                                      unitPrice: Number(event.target.value) || 0,
                                      discountPercent: null,
                                    };
                                    updateRow(index, { priceTiers: next });
                                  }}
                                  className="admin-input w-28 text-xs"
                                  placeholder="Price"
                                />
                                <button
                                  type="button"
                                  className="text-[10px] font-medium text-red-600"
                                  onClick={() => {
                                    const next = (row.priceTiers ?? []).filter(
                                      (_, i) => i !== tierIndex,
                                    );
                                    updateRow(index, { priceTiers: next });
                                  }}
                                >
                                  ×
                                </button>
                              </div>
                            ))}
                            <button
                              type="button"
                              className="text-[11px] font-semibold text-[#8b6914]"
                              onClick={() => {
                                const next: ProductPriceTierDraft[] = [
                                  ...(row.priceTiers ?? []),
                                  {
                                    minQuantity: ((row.priceTiers ?? []).at(-1)?.minQuantity ?? 1) + 4,
                                    tierType: "fixed_unit",
                                    unitPrice: row.price ?? basePrice,
                                    discountPercent: null,
                                  },
                                ];
                                updateRow(index, { priceTiers: next });
                              }}
                            >
                              + Tier
                            </button>
                          </div>
                        </td>
                      ) : null}
                      <td className="px-3 py-2 align-middle">
                        <button
                          type="button"
                          onClick={() => removeRow(index)}
                          className="text-xs font-medium text-red-600 hover:text-red-700"
                        >
                          Remove
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-sm text-zinc-500">
              Select attribute values, then generate sellable configurations.
            </p>
          )}
        </div>
      ) : null}
    </section>
  );
}
