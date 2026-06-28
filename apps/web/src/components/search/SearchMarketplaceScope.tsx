"use client";

import type { ProductOrigin } from "@/lib/types/catalog";

export type SearchMarketplaceScope = ProductOrigin | "all";

interface SearchMarketplaceScopeProps {
  value: SearchMarketplaceScope;
  onChange: (value: SearchMarketplaceScope) => void;
  className?: string;
}

const OPTIONS: { id: SearchMarketplaceScope; label: string }[] = [
  { id: "all", label: "All" },
  { id: "china", label: "China" },
  { id: "tz", label: "Buy from Dar" },
];

export function SearchMarketplaceScope({
  value,
  onChange,
  className = "",
}: SearchMarketplaceScopeProps) {
  return (
    <div className={`flex flex-wrap gap-1.5 px-2 ${className}`} role="group" aria-label="Search marketplace">
      {OPTIONS.map((option) => {
        const isActive = value === option.id;
        return (
          <button
            key={option.id}
            type="button"
            onClick={() => onChange(option.id)}
            className={`rounded-full px-3 py-1 text-[11px] font-semibold transition ${
              isActive
                ? "bg-[#c9a227] text-zinc-900 shadow-sm"
                : "bg-zinc-100 text-zinc-600 hover:bg-zinc-200 hover:text-zinc-900"
            }`}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

export function scopeToOrigin(scope: SearchMarketplaceScope): ProductOrigin | undefined {
  return scope === "all" ? undefined : scope;
}
