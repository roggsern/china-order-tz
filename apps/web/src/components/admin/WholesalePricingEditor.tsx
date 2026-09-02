"use client";

import type { ProductPriceTierDraft } from "@/lib/types/catalog";
import {
  VOLUME_PRICING_EDITOR_DESCRIPTION,
  VOLUME_PRICING_EDITOR_TITLE,
  firstVolumePricingFormError,
  inferredVolumeRangeLabels,
} from "@/lib/admin/volume-pricing-tiers";

interface WholesalePricingEditorProps {
  enabled: boolean;
  onEnabledChange: (enabled: boolean) => void;
  tiers: ProductPriceTierDraft[];
  onChange: (tiers: ProductPriceTierDraft[]) => void;
  basePrice: number;
  title?: string;
  description?: string;
  aggregatesVariants?: boolean;
  configurationTiersNote?: string | null;
  disabled?: boolean;
}

function formatTzs(amount: number): string {
  return `TZS ${Math.round(amount).toLocaleString("en-US")}`;
}

function starterTier(basePrice: number, minQuantity: number): ProductPriceTierDraft {
  return {
    minQuantity,
    tierType: "fixed_unit",
    unitPrice: Math.max(0, Math.round(basePrice * 0.8)),
    discountPercent: null,
  };
}

export function WholesalePricingEditor({
  enabled,
  onEnabledChange,
  tiers,
  onChange,
  basePrice,
  title = VOLUME_PRICING_EDITOR_TITLE,
  description = VOLUME_PRICING_EDITOR_DESCRIPTION,
  aggregatesVariants = false,
  configurationTiersNote = null,
  disabled = false,
}: WholesalePricingEditorProps) {
  function updateTier(index: number, patch: Partial<ProductPriceTierDraft>) {
    onChange(tiers.map((tier, i) => (i === index ? { ...tier, ...patch } : tier)));
  }

  function addTier() {
    const lastMin = tiers[tiers.length - 1]?.minQuantity ?? 5;
    onChange([...tiers, starterTier(basePrice, lastMin + 10)]);
  }

  function removeTier(index: number) {
    onChange(tiers.filter((_, i) => i !== index));
  }

  const rangeLabels = enabled ? inferredVolumeRangeLabels(tiers, formatTzs) : [];
  const formError = firstVolumePricingFormError(enabled, tiers);

  return (
    <section className="admin-card space-y-4 p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-zinc-900">{title}</h2>
          <p className="mt-1 text-xs text-zinc-500">{description}</p>
          {aggregatesVariants ? (
            <p className="mt-1 text-xs text-zinc-500">
              Different variants of this product count together toward these thresholds.
            </p>
          ) : null}
        </div>
        <label className="inline-flex items-center gap-2 text-xs font-semibold text-zinc-700">
          <input
            type="checkbox"
            checked={enabled}
            disabled={disabled}
            onChange={(event) => {
              const next = event.target.checked;
              onEnabledChange(next);
              if (next && tiers.length === 0) {
                onChange([starterTier(basePrice, 10)]);
              }
              if (!next) {
                onChange([]);
              }
            }}
            className="h-4 w-4 rounded border-zinc-300 text-[#c9a227] focus:ring-[#c9a227]"
          />
          Enable bulk / volume tiers
        </label>
      </div>

      {enabled ? (
        <div className="space-y-3">
          {tiers.map((tier, index) => (
            <div
              key={`${tier.id ?? "new"}-${index}`}
              className="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50/70 p-3 sm:grid-cols-12"
            >
              <div className="sm:col-span-2">
                <label className="admin-label">Min qty</label>
                <input
                  type="number"
                  min={1}
                  disabled={disabled}
                  value={tier.minQuantity}
                  onChange={(event) =>
                    updateTier(index, { minQuantity: Number(event.target.value) || 1 })
                  }
                  className="admin-input mt-1"
                />
              </div>
              <div className="sm:col-span-3">
                <label className="admin-label">Pricing type</label>
                <select
                  disabled={disabled}
                  value={tier.tierType}
                  onChange={(event) => {
                    const nextType = event.target.value as ProductPriceTierDraft["tierType"];
                    updateTier(index, {
                      tierType: nextType,
                      unitPrice: nextType === "fixed_unit" ? tier.unitPrice ?? basePrice : null,
                      discountPercent: nextType === "percent_off" ? tier.discountPercent ?? 5 : null,
                    });
                  }}
                  className="admin-input mt-1"
                >
                  <option value="fixed_unit">Fixed unit price</option>
                  <option value="percent_off">Percentage off</option>
                </select>
              </div>
              {tier.tierType === "fixed_unit" ? (
                <div className="sm:col-span-4">
                  <label className="admin-label">Unit price (TZS)</label>
                  <input
                    type="number"
                    min={0}
                    disabled={disabled}
                    value={tier.unitPrice ?? ""}
                    onChange={(event) =>
                      updateTier(index, { unitPrice: Number(event.target.value) || 0 })
                    }
                    className="admin-input mt-1"
                  />
                </div>
              ) : (
                <div className="sm:col-span-4">
                  <label className="admin-label">Discount %</label>
                  <input
                    type="number"
                    min={0}
                    max={100}
                    step={0.01}
                    disabled={disabled}
                    value={tier.discountPercent ?? ""}
                    onChange={(event) =>
                      updateTier(index, {
                        discountPercent: Number(event.target.value) || 0,
                      })
                    }
                    className="admin-input mt-1"
                  />
                </div>
              )}
              <div className="flex items-end sm:col-span-3">
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => removeTier(index)}
                  className="admin-btn-secondary w-full"
                >
                  Remove
                </button>
              </div>
            </div>
          ))}

          <button
            type="button"
            disabled={disabled}
            onClick={addTier}
            className="admin-btn-secondary"
          >
            Add pricing tier
          </button>

          {rangeLabels.length > 0 ? (
            <p className="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700">
              Customer unit price: {rangeLabels.join("; ")}. Quantities between thresholds stay valid.
            </p>
          ) : null}

          {formError ? <p className="text-xs text-red-600">{formError}</p> : null}
        </div>
      ) : null}

      {configurationTiersNote ? (
        <p className="text-xs text-amber-800">{configurationTiersNote}</p>
      ) : null}
    </section>
  );
}
