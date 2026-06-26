"use client";

import type { ProductVariants } from "@/lib/types/catalog";
import {
  formatCommaList,
  normalizeProductSizes,
  parseCommaList,
  VARIANT_PRESETS,
} from "@/lib/catalog/variants";
import { SizeCheckboxSelector } from "@/components/admin/SizeCheckboxSelector";

interface ProductVariantSectionProps {
  variants: ProductVariants;
  onChange: (variants: ProductVariants) => void;
}

const OTHER_FIELDS = [
  { key: "colors" as const, label: "Colors", placeholder: "Red, Blue, Black", presets: VARIANT_PRESETS.colors },
  { key: "storage" as const, label: "Storage", placeholder: "128GB, 256GB, 512GB", presets: VARIANT_PRESETS.storage },
];

export function ProductVariantSection({ variants, onChange }: ProductVariantSectionProps) {
  const updateSizes = (sizes: string[]) => {
    const cleaned = normalizeProductSizes(sizes);
    onChange({
      ...variants,
      sizes: cleaned.length > 0 ? cleaned : undefined,
    });
  };

  const updateField = (key: "colors" | "storage", value: string) => {
    const parsed = parseCommaList(value);
    onChange({
      ...variants,
      [key]: parsed.length > 0 ? parsed : undefined,
    });
  };

  const applyPresets = (key: "colors" | "storage", presets: readonly string[]) => {
    onChange({
      ...variants,
      [key]: [...presets],
    });
  };

  return (
    <section className="admin-card p-5">
      <h2 className="text-sm font-semibold text-zinc-900">Product variants</h2>
      <p className="mt-1 text-xs text-zinc-500">
        Optional size, color, and storage options. Price and stock are not affected.
      </p>

      <div className="mt-4 space-y-6">
        <SizeCheckboxSelector
          selected={variants.sizes ?? []}
          onChange={updateSizes}
        />

        {OTHER_FIELDS.map(({ key, label, placeholder, presets }) => (
          <div key={key}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <label className="admin-label" htmlFor={`variant-${key}`}>
                {label}
              </label>
              <button
                type="button"
                onClick={() => applyPresets(key, presets)}
                className="rounded-md border border-zinc-300 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
              >
                Use presets
              </button>
            </div>
            <input
              id={`variant-${key}`}
              type="text"
              value={formatCommaList(variants[key])}
              onChange={(event) => updateField(key, event.target.value)}
              className="admin-input mt-1.5"
              placeholder={placeholder}
            />
          </div>
        ))}
      </div>
    </section>
  );
}
