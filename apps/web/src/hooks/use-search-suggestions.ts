"use client";

import { useEffect, useState } from "react";
import { SEARCH_DEBOUNCE_MS } from "@/lib/search/constants";
import { clearSearchQueryCache } from "@/lib/search/search-engine";
import { fetchUnifiedSearchSuggest } from "@/lib/api/marketplace-search";
import { mapUnifiedSuggestToSearchResults } from "@/lib/search/map-unified-suggest";
import { getRecentSearches } from "@/lib/search/recent-searches";
import type { SearchResults } from "@/lib/search/types";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";
import { scopeToOrigin } from "@/components/search/SearchMarketplaceScope";

const EMPTY_RESULTS: SearchResults = {
  products: [],
  groups: [],
  categories: [],
  brands: [],
  stores: [],
  terms: [],
};

/**
 * Live search suggestions via unified marketplace suggest API.
 * Scope tabs map to API scope=all|china|tz.
 */
export function useSearchSuggestions(
  query: string,
  enabled = true,
  scope: SearchMarketplaceScope = "all",
) {
  const [results, setResults] = useState<SearchResults>(EMPTY_RESULTS);
  const [isLoading, setIsLoading] = useState(false);
  const [recentSearches, setRecentSearches] = useState<string[]>([]);
  const debouncedQuery = useDebouncedValue(query, SEARCH_DEBOUNCE_MS);
  const origin = scopeToOrigin(scope);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const refreshRecent = () => setRecentSearches(getRecentSearches());
    refreshRecent();

    window.addEventListener("recent-searches-updated", refreshRecent);
    return () => window.removeEventListener("recent-searches-updated", refreshRecent);
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const trimmed = debouncedQuery.trim();

    if (!trimmed) {
      clearSearchQueryCache();
      setResults(EMPTY_RESULTS);
      setIsLoading(false);
      return;
    }

    let cancelled = false;
    setIsLoading(true);

    void (async () => {
      try {
        const payload = await fetchUnifiedSearchSuggest({
          q: trimmed,
          scope,
        });
        if (cancelled) return;

        clearSearchQueryCache();
        setResults(mapUnifiedSuggestToSearchResults(payload));
      } catch {
        if (cancelled) return;
        clearSearchQueryCache();
        setResults(EMPTY_RESULTS);
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [debouncedQuery, enabled, scope]);

  const isSearching = enabled && query.trim().length > 0 && debouncedQuery !== query;

  return {
    results,
    recentSearches,
    isLoading: isLoading && !isSearching,
    isSearching,
    catalogReady: true,
    debouncedQuery,
    origin,
  };
}
