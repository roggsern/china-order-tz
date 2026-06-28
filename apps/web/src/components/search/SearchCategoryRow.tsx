"use client";

import Link from "next/link";
import type { Category } from "@/lib/types/catalog";
import { buildSearchCategoryHref } from "@/lib/search/search-url";

interface SearchCategoryRowProps {
  category: Category;
  onSelect: () => void;
}

export function SearchCategoryRow({ category, onSelect }: SearchCategoryRowProps) {
  return (
    <Link
      href={buildSearchCategoryHref(category.slug)}
      onClick={onSelect}
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
