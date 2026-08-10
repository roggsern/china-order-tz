"use client";

import Link from "next/link";
import type { Category, ProductOrigin } from "@/lib/types/catalog";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";
import {
  buildSearchBrandHref,
  buildSearchCategorySuggestionHref,
  buildSearchStoreHref,
} from "@/lib/search/search-url";

interface SearchCategoryRowProps {
  category: Category;
  /** Marketplace tab for category → /search; brand hrefs still use origin. */
  marketplaceScope?: SearchMarketplaceScope;
  /** @deprecated Prefer marketplaceScope; kept for brand listing context. */
  origin?: ProductOrigin;
  onSelect: (href: string) => void;
}

function scopeToBrandOrigin(
  scope: SearchMarketplaceScope,
  origin?: ProductOrigin,
): ProductOrigin | undefined {
  if (origin === "china" || origin === "tz") {
    return origin;
  }
  if (scope === "china" || scope === "tz") {
    return scope;
  }
  return undefined;
}

export function SearchCategoryRow({
  category,
  marketplaceScope = "all",
  origin,
  onSelect,
}: SearchCategoryRowProps) {
  const brandOrigin = scopeToBrandOrigin(marketplaceScope, origin);
  const href =
    category.searchSuggestionType === "brand"
      ? buildSearchBrandHref(category.slug, brandOrigin)
      : category.searchSuggestionType === "store"
        ? buildSearchStoreHref(category.slug)
        : buildSearchCategorySuggestionHref(category.name, marketplaceScope);

  return (
    <Link
      href={href}
      onClick={() => onSelect(href)}
      className="flex items-center gap-3 rounded-xl px-2 py-2.5 transition hover:bg-zinc-50 active:bg-zinc-100"
    >
      <span
        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-lg ${category.gradient}`}
        aria-hidden
      >
        {category.icon}
      </span>
      <div className="min-w-0">
        <p className="text-sm font-semibold text-zinc-900">{category.name}</p>
        <p className="truncate text-xs text-zinc-500">{category.description}</p>
      </div>
    </Link>
  );
}
