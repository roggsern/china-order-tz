"use client";

import { DEFAULT_SIZE_OPTIONS, normalizeProductSizes, toggleSizeSelection } from "@/lib/catalog/variants";

interface SizeCheckboxSelectorProps {
  selected: string[];
  onChange: (sizes: string[]) => void;
}

export function SizeCheckboxSelector({ selected, onChange }: SizeCheckboxSelectorProps) {
  const normalizedSelected = normalizeProductSizes(selected);
  const presetSet = new Set<string>(DEFAULT_SIZE_OPTIONS);
  const customSizes = normalizedSelected.filter((size) => !presetSet.has(size));

  const handleToggle = (size: string) => {
    onChange(toggleSizeSelection(normalizedSelected, size));
  };

  const removeCustomSize = (size: string) => {
    onChange(normalizeProductSizes(normalizedSelected.filter((entry) => entry !== size)));
  };

  const selectAll = () => {
    onChange(normalizeProductSizes([...DEFAULT_SIZE_OPTIONS, ...customSizes]));
  };

  const clearAll = () => {
    onChange([]);
  };

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="admin-label">Sizes</span>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={selectAll}
            className="rounded-md border border-zinc-300 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Select All Sizes
          </button>
          <button
            type="button"
            onClick={clearAll}
            className="rounded-md border border-zinc-300 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Clear Sizes
          </button>
        </div>
      </div>

      <p className="mt-1 text-xs text-zinc-500">
        Choose which sizes apply to this product. Leave none selected if the product has no sizes.
      </p>

      <div className="mt-3 flex flex-wrap gap-2">
        {DEFAULT_SIZE_OPTIONS.map((size) => {
          const isSelected = normalizedSelected.includes(size);

          return (
            <label
              key={size}
              className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition ${
                isSelected
                  ? "border-zinc-900 bg-zinc-900 text-white"
                  : "border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400"
              }`}
            >
              <input
                type="checkbox"
                checked={isSelected}
                onChange={() => handleToggle(size)}
                className="sr-only"
              />
              {size}
            </label>
          );
        })}
      </div>

      {customSizes.length > 0 && (
        <div className="mt-3">
          <p className="text-xs font-medium text-zinc-600">Legacy custom sizes</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {customSizes.map((size) => (
              <button
                key={size}
                type="button"
                onClick={() => removeCustomSize(size)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900"
                title="Remove custom size"
              >
                {size}
                <span aria-hidden>×</span>
              </button>
            ))}
          </div>
        </div>
      )}

      {normalizedSelected.length > 0 && (
        <p className="mt-3 text-xs text-zinc-600">
          Selected ({normalizedSelected.length}): {normalizedSelected.join(", ")}
        </p>
      )}
    </div>
  );
}
