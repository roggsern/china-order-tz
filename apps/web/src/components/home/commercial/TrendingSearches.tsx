"use client";

import Link from "next/link";
import { buildProductSearchHref } from "@/lib/search/search-url";

type TrendingSearchesProps = {
  terms: string[];
  className?: string;
};

export function TrendingSearches({ terms, className = "" }: TrendingSearchesProps) {
  if (terms.length === 0) {
    return null;
  }

  return (
    <div className={`flex flex-wrap items-center gap-x-2 gap-y-1 ${className}`}>
      <span className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-400">
        Trending
      </span>
      {terms.map((term) => (
        <Link
          key={term}
          href={buildProductSearchHref(term)}
          className="rounded-full px-2 py-0.5 text-[12px] font-medium text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-900"
        >
          {term}
        </Link>
      ))}
    </div>
  );
}
