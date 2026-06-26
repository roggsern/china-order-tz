"use client";

import { useEffect, useId, useRef, useState, type KeyboardEvent } from "react";
import {
  filterTanzaniaRegions,
  isValidTanzaniaRegion,
  type TanzaniaRegion,
} from "@/lib/checkout/tanzania-regions";
import { checkoutInputClass } from "./CheckoutField";

interface SearchableRegionSelectProps {
  id: string;
  value: string;
  onChange: (value: string) => void;
  onBlur?: () => void;
  error?: string;
}

export function SearchableRegionSelect({
  id,
  value,
  onChange,
  onBlur,
  error,
}: SearchableRegionSelectProps) {
  const listboxId = useId();
  const containerRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState(value);
  const [highlightIndex, setHighlightIndex] = useState(0);

  const options = filterTanzaniaRegions(query);

  useEffect(() => {
    setQuery(value);
  }, [value]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
        onBlur?.();
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [open, onBlur]);

  useEffect(() => {
    setHighlightIndex(0);
  }, [query, open]);

  const selectRegion = (region: TanzaniaRegion) => {
    onChange(region);
    setQuery(region);
    setOpen(false);
  };

  const handleInputChange = (nextQuery: string) => {
    setQuery(nextQuery);
    setOpen(true);
    if (isValidTanzaniaRegion(nextQuery)) {
      onChange(nextQuery);
    } else if (value) {
      onChange("");
    }
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (!open && (event.key === "ArrowDown" || event.key === "Enter")) {
      setOpen(true);
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setHighlightIndex((index) => Math.min(index + 1, options.length - 1));
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setHighlightIndex((index) => Math.max(index - 1, 0));
    } else if (event.key === "Enter" && options[highlightIndex]) {
      event.preventDefault();
      selectRegion(options[highlightIndex]);
    } else if (event.key === "Escape") {
      setOpen(false);
    }
  };

  const inputClass = `${checkoutInputClass} ${
    error
      ? "border-red-300 bg-red-50/40 focus:border-red-400 focus:ring-red-200"
      : ""
  }`;

  return (
    <div ref={containerRef} className="relative">
      <input
        id={id}
        type="text"
        role="combobox"
        autoComplete="address-level1"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-autocomplete="list"
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${id}-error` : undefined}
        value={query}
        onChange={(event) => handleInputChange(event.target.value)}
        onFocus={() => setOpen(true)}
        onBlur={() => {
          if (!open) {
            onBlur?.();
          }
        }}
        onKeyDown={handleKeyDown}
        placeholder="Search region…"
        className={inputClass}
      />

      {open && options.length > 0 && (
        <ul
          id={listboxId}
          role="listbox"
          className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-lg shadow-zinc-900/10"
        >
          {options.map((region, index) => (
            <li key={region} role="option" aria-selected={value === region}>
              <button
                type="button"
                className={`flex w-full px-4 py-2.5 text-left text-sm transition ${
                  index === highlightIndex || value === region
                    ? "bg-[#c9a227]/10 font-medium text-zinc-900"
                    : "text-zinc-700 hover:bg-zinc-50"
                }`}
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => selectRegion(region)}
              >
                {region}
              </button>
            </li>
          ))}
        </ul>
      )}

      {open && query && options.length === 0 && (
        <div className="absolute z-20 mt-1 w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-500 shadow-lg">
          No regions match &ldquo;{query}&rdquo;
        </div>
      )}
    </div>
  );
}
