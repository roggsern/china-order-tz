"use client";

import Link from "next/link";
import type { SearchResults } from "@/lib/search/types";
import { POPULAR_SEARCHES, TRENDING_SEARCHES } from "@/lib/search/constants";
import { SearchProductRow } from "./SearchProductRow";
import { SearchCategoryRow } from "./SearchCategoryRow";

interface SearchResultsPanelProps {
  query: string;
  results: SearchResults;
  recentSearches: string[];
  isLoading?: boolean;
  isSearching?: boolean;
  onSelect: (href: string, label?: string) => void;
  onClearRecent?: () => void;
  className?: string;
}

function TermChip({
  label,
  href,
  onSelect,
}: {
  label: string;
  href: string;
  onSelect: (href: string, label?: string) => void;
}) {
  return (
    <Link
      href={href}
      onClick={() => onSelect(href, label)}
      className="inline-flex items-center rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:border-[#c9a227]/40 hover:bg-amber-50/50 hover:text-[#8b6914] active:scale-95"
    >
      {label}
    </Link>
  );
}

function SectionHeading({ children }: { children: React.ReactNode }) {
  return (
    <p className="px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">{children}</p>
  );
}

export function SearchResultsPanel({
  query,
  results,
  recentSearches,
  isLoading,
  isSearching,
  onSelect,
  onClearRecent,
  className = "",
}: SearchResultsPanelProps) {
  const trimmed = query.trim();
  const hasQuery = trimmed.length > 0;
  const hasProducts = results.products.length > 0;
  const hasCategories = results.categories.length > 0;
  const hasTerms = results.terms.length > 0;
  const showEmpty = hasQuery && !hasProducts && !hasCategories && !hasTerms && !isLoading && !isSearching;

  return (
    <div className={`overflow-hidden ${className}`}>
      <div className="max-h-[min(70vh,520px)] overflow-y-auto overscroll-contain px-2 py-3 sm:px-3">
        {(isLoading || isSearching) && (
          <div className="flex items-center gap-2 px-2 py-4 text-sm text-zinc-500">
            <span className="h-4 w-4 animate-spin rounded-full border-2 border-[#c9a227] border-t-transparent" />
            Searching…
          </div>
        )}

        {!hasQuery && recentSearches.length > 0 && (
          <section className="mb-4">
            <div className="mb-2 flex items-center justify-between px-2">
              <SectionHeading>Recent searches</SectionHeading>
              {onClearRecent ? (
                <button
                  type="button"
                  onClick={onClearRecent}
                  className="text-[10px] font-semibold text-zinc-400 transition hover:text-[#8b6914]"
                >
                  Clear
                </button>
              ) : null}
            </div>
            <div className="flex flex-wrap gap-2 px-2">
              {recentSearches.map((term) => (
                <TermChip
                  key={term}
                  label={term}
                  href={`/products?q=${encodeURIComponent(term)}`}
                  onSelect={onSelect}
                />
              ))}
            </div>
          </section>
        )}

        {!hasQuery && (
          <>
            <section className="mb-4">
              <div className="mb-2 px-2">
                <SectionHeading>Trending</SectionHeading>
              </div>
              <div className="flex flex-wrap gap-2 px-2">
                {TRENDING_SEARCHES.map((term) => (
                  <TermChip
                    key={term}
                    label={term}
                    href={`/products?q=${encodeURIComponent(term)}`}
                    onSelect={onSelect}
                  />
                ))}
              </div>
            </section>

            <section className="mb-2">
              <div className="mb-2 px-2">
                <SectionHeading>Popular</SectionHeading>
              </div>
              <div className="flex flex-wrap gap-2 px-2">
                {POPULAR_SEARCHES.map((term) => (
                  <TermChip
                    key={term}
                    label={term}
                    href={`/products?q=${encodeURIComponent(term)}`}
                    onSelect={onSelect}
                  />
                ))}
              </div>
            </section>
          </>
        )}

        {hasQuery && hasProducts && (
          <section className="mb-3">
            <div className="mb-1 px-2">
              <SectionHeading>Products</SectionHeading>
            </div>
            <div className="space-y-0.5">
              {results.products.map((product) => (
                <SearchProductRow
                  key={product.id}
                  product={product}
                  onSelect={() => onSelect(`/products/${product.slug}`, product.name)}
                />
              ))}
            </div>
          </section>
        )}

        {hasQuery && hasCategories && (
          <section className="mb-3">
            <div className="mb-1 px-2">
              <SectionHeading>Categories</SectionHeading>
            </div>
            <div className="space-y-0.5">
              {results.categories.map((category) => (
                <SearchCategoryRow
                  key={category.slug}
                  category={category}
                  onSelect={() => onSelect(`/categories/${category.slug}`, category.name)}
                />
              ))}
            </div>
          </section>
        )}

        {hasQuery && hasTerms && (
          <section className="mb-2">
            <div className="mb-2 px-2">
              <SectionHeading>Suggestions</SectionHeading>
            </div>
            <div className="flex flex-wrap gap-2 px-2">
              {results.terms.map((term) => (
                <TermChip key={term.label} label={term.label} href={term.href} onSelect={onSelect} />
              ))}
            </div>
          </section>
        )}

        {showEmpty && (
          <div className="px-2 py-8 text-center">
            <p className="text-sm font-medium text-zinc-700">No results for &ldquo;{trimmed}&rdquo;</p>
            <p className="mt-1 text-xs text-zinc-500">Try a different keyword or browse categories.</p>
            <Link
              href={`/products?q=${encodeURIComponent(trimmed)}`}
              onClick={() => onSelect(`/products?q=${encodeURIComponent(trimmed)}`, trimmed)}
              className="mt-4 inline-flex text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
            >
              View all results →
            </Link>
          </div>
        )}

        {hasQuery && (hasProducts || hasCategories) && (
          <div className="border-t border-zinc-100 px-2 pt-3">
            <Link
              href={`/products?q=${encodeURIComponent(trimmed)}`}
              onClick={() => onSelect(`/products?q=${encodeURIComponent(trimmed)}`, trimmed)}
              className="flex w-full items-center justify-center rounded-xl bg-zinc-900 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
            >
              View all results for &ldquo;{trimmed}&rdquo;
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
