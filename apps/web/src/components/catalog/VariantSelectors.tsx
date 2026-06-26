"use client";

import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import { getProductSizes, getProductVariants } from "@/lib/catalog/variants";

interface VariantSelectorsProps {
  product: Product;
  variant: ProductVariantChoice;
  onChange: (variant: ProductVariantChoice) => void;
}

export function VariantSelectors({ product, variant, onChange }: VariantSelectorsProps) {
  const options = getProductVariants(product);
  const sizes = getProductSizes(product);
  const colorOptions = options.colors ?? [];
  const storageOptions = options.storage ?? [];

  const handleSizeSelect = (value: string) => {
    onChange({
      ...variant,
      size: variant.size === value ? undefined : value,
    });
  };

  const handleSelect = (key: "color" | "storage", value: string) => {
    onChange({
      ...variant,
      [key]: variant[key] === value ? undefined : value,
    });
  };

  const hasAnyOptions = sizes.length > 0 || colorOptions.length > 0 || storageOptions.length > 0;
  if (!hasAnyOptions) return null;

  return (
    <div className="space-y-5">
      {sizes.length > 0 && (
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">Size</p>
          <div className="mt-2.5 flex flex-wrap gap-2 sm:gap-2.5">
            {sizes.map((value) => {
              const isSelected = variant.size === value;

              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => handleSizeSelect(value)}
                  aria-pressed={isSelected}
                  aria-label={`Size ${value}`}
                  className={`min-h-10 min-w-[2.75rem] rounded-lg border px-3 py-2 text-sm font-medium transition sm:px-4 ${
                    isSelected
                      ? "border-zinc-900 bg-zinc-900 text-white"
                      : "border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400"
                  }`}
                >
                  {value}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {colorOptions.length > 0 && (
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">Color</p>
          <div className="mt-2.5 flex flex-wrap gap-2">
            {colorOptions.map((value) => {
              const isSelected = variant.color === value;

              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => handleSelect("color", value)}
                  aria-pressed={isSelected}
                  className={`rounded-lg border px-4 py-2 text-sm font-medium transition ${
                    isSelected
                      ? "border-zinc-900 bg-zinc-900 text-white"
                      : "border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400"
                  }`}
                >
                  {value}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {storageOptions.length > 0 && (
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">Storage</p>
          <div className="mt-2.5 flex flex-wrap gap-2">
            {storageOptions.map((value) => {
              const isSelected = variant.storage === value;

              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => handleSelect("storage", value)}
                  aria-pressed={isSelected}
                  className={`rounded-lg border px-4 py-2 text-sm font-medium transition ${
                    isSelected
                      ? "border-zinc-900 bg-zinc-900 text-white"
                      : "border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400"
                  }`}
                >
                  {value}
                </button>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
