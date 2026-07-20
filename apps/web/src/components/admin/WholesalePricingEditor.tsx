"use client";

import type { ProductPriceTierDraft } from "@/lib/types/catalog";

interface WholesalePricingEditorProps {
  enabled: boolean;
  onEnabledChange: (enabled: boolean) => void;
  tiers: ProductPriceTierDraft[];
  onChange: (tiers: ProductPriceTierDraft[]) => void;
  basePrice: number;
  title?: string;
  description?: string;
}

function emptyTier(basePrice: number): ProductPriceTierDraft {
  return {
    minQuantity: 5,
    tierType: "fixed_unit",
    unitPrice: Math.max(0, Math.round(basePrice * 0.93)),
    discountPercent: null,
  };
}

export function WholesalePricingEditor({
  enabled,
  onEnabledChange,
  tiers,
  onChange,
  basePrice,
  title = "Wholesale pricing",
  description = "Quantity tiers unlock lower unit prices at checkout and on the product page.",
}: WholesalePricingEditorProps) {
  function updateTier(index: number, patch: Partial<ProductPriceTierDraft>) {
    onChange(tiers.map((tier, i) => (i === index ? { ...tier, ...patch } : tier)));
  }

  function addTier() {
    const lastMin = tiers[tiers.length - 1]?.minQuantity ?? 1;
    onChange([
      ...tiers,
      {
        minQuantity: lastMin + 5,
        tierType: "fixed_unit",
        unitPrice: Math.max(0, Math.round(basePrice * 0.9)),
        discountPercent: null,
      },
    ]);
  }

  function removeTier(index: number) {
    onChange(tiers.filter((_, i) => i !== index));
  }

  return (
    <section className="admin-card space-y-4 p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-zinc-900">{title}</h2>
          <p className="mt-1 text-xs text-zinc-500">{description}</p>
        </div>
        <label className="inline-flex items-center gap-2 text-xs font-semibold text-zinc-700">
          <input
            type="checkbox"
            checked={enabled}
            onChange={(event) => {
              const next = event.target.checked;
              onEnabledChange(next);
              if (next && tiers.length === 0) {
                onChange([
                  {
                    minQuantity: 1,
                    tierType: "fixed_unit",
                    unitPrice: basePrice,
                    discountPercent: null,
                  },
                  emptyTier(basePrice),
                ]);
              }
              if (!next) {
                onChange([]);
              }
            }}
            className="h-4 w-4 rounded border-zinc-300 text-[#c9a227] focus:ring-[#c9a227]"
          />
          Enable wholesale tiers
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
                  value={tier.minQuantity}
                  onChange={(event) =>
                    updateTier(index, { minQuantity: Number(event.target.value) || 1 })
                  }
                  className="admin-input mt-1"
                />
              </div>
              <div className="sm:col-span-3">
                <label className="admin-label">Type</label>
                <select
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
                  onClick={() => removeTier(index)}
                  className="admin-btn-secondary w-full"
                >
                  Remove
                </button>
              </div>
            </div>
          ))}

          <button type="button" onClick={addTier} className="admin-btn-secondary">
            Add tier
          </button>
        </div>
      ) : null}
    </section>
  );
}
