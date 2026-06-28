"use client";

import Link from "next/link";
import type { ProductOrigin } from "@/lib/types/catalog";
import type { SearchResults } from "@/lib/search/types";
import { POPULAR_SEARCHES, TRENDING_SEARCHES } from "@/lib/search/constants";
import { buildProductSearchHref } from "@/lib/search/search-url";
import { SearchProductRow } from "./SearchProductRow";
import { SearchCategoryRow } from "./SearchCategoryRow";
import { SearchResultsSkeleton } from "./SearchResultsSkeleton";
import {
  SearchMarketplaceScope,
  type SearchMarketplaceScope as SearchScope,
} from "./SearchMarketplaceScope";

interface SearchResultsPanelProps {
  query: string;
  results: SearchResults;
  recentSearches: string[];
  isLoading?: boolean;
  isSearching?: boolean;
  origin?: ProductOrigin;
  marketplaceScope?: SearchScope;
  onMarketplaceScopeChange?: (scope: SearchScope) => void;
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
  origin,
  marketplaceScope = "all",
  onMarketplaceScopeChange,
  onSelect,
  onClearRecent,
  className = "",
}: SearchResultsPanelProps) {
  const trimmed = query.trim();
  const hasQuery = trimmed.length > 0;
  const hasProducts = results.products.length > 0;
  const hasGroups = results.groups.some((group) => group.products.length > 0);
  const hasCategories = results.categories.length > 0;
  const hasTerms = results.terms.length > 0;
  const showLoadingState = isLoading || isSearching;
  const showEmpty =
    hasQuery && !hasProducts && !hasCategories && !hasTerms && !showLoadingState;
  const resultsHref = buildProductSearchHref(trimmed, origin);

  return (
    <div className={`overflow-hidden ${className}`}>
      {onMarketplaceScopeChange ? (
        <div className="border-b border-zinc-100 py-3">
          <p className="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-400">
            Search in
          </p>
          <SearchMarketplaceScope value={marketplaceScope} onChange={onMarketplaceScopeChange} />
        </div>
      ) : null}

      <div className="max-h-[min(70vh,520px)] overflow-y-auto overscroll-contain px-2 py-3 sm:px-3">
        {showLoadingState && <SearchResultsSkeleton />}

        {!showLoadingState && !hasQuery && recentSearches.length > 0 && (
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
                  href={buildProductSearchHref(term, origin)}
                  onSelect={onSelect}
                />
              ))}
            </div>
          </section>
        )}

        {!showLoadingState && !hasQuery && (
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
                    href={buildProductSearchHref(term, origin)}
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
                    href={buildProductSearchHref(term, origin)}
                    onSelect={onSelect}
                  />
                ))}
              </div>
            </section>
          </>
        )}

        {!showLoadingState && hasQuery && hasGroups && (
          <>
            {results.groups.map((group) => (
              <section key={group.tier} className="mb-3">
                <div className="mb-1 px-2">
                  <SectionHeading>{group.label}</SectionHeading>
                </div>
                <div className="space-y-0.5">
                  {group.products.map((product) => (
                    <SearchProductRow
                      key={product.id}
                      product={product}
                      onSelect={() => onSelect(`/products/${product.slug}`, product.name)}
                    />
                  ))}
                </div>
              </section>
            ))}
          </>
        )}

        {!showLoadingState && hasQuery && !hasGroups && hasProducts && (
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

        {!showLoadingState && hasQuery && hasCategories && (
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

        {!showLoadingState && hasQuery && hasTerms && (
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
          <div className="px-2 py-10 text-center">
            <span className="text-3xl" aria-hidden>
              🔍
            </span>
            <p className="mt-3 text-sm font-semibold text-zinc-800">No results found</p>
            <p className="mt-1 text-xs text-zinc-500">
              No matches for &ldquo;{trimmed}&rdquo;
              {origin === "china"
                ? " in China products"
                : origin === "tz"
                  ? " in Buy from Dar"
                  : ""}
              . Try another keyword or switch marketplace.
            </p>
            <Link
              href={resultsHref}
              onClick={() => onSelect(resultsHref, trimmed)}
              className="mt-4 inline-flex text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
            >
              View all results {"→"}
            </Link>
          </div>
        )}

        {!showLoadingState && hasQuery && (hasProducts || hasCategories) && (
          <div className="border-t border-zinc-100 px-2 pt-3">
            <Link
              href={resultsHref}
              onClick={() => onSelect(resultsHref, trimmed)}
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
