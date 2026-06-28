"use client";

import { useEffect, useMemo, useState } from "react";
import { SEARCH_DEBOUNCE_MS } from "@/lib/search/constants";
import { getRecentSearches } from "@/lib/search/recent-searches";
import { clearSearchQueryCache, searchCatalog } from "@/lib/search/search-engine";
import type { SearchResults } from "@/lib/search/types";
import type { Product } from "@/lib/types/catalog";
import { productService } from "@/lib/services/product-service.client";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";
import { scopeToOrigin } from "@/components/search/SearchMarketplaceScope";

const EMPTY_RESULTS: SearchResults = {
  products: [],
  groups: [],
  categories: [],
  terms: [],
};

export function useSearchSuggestions(
  query: string,
  enabled = true,
  scope: SearchMarketplaceScope = "all",
) {
  const [catalog, setCatalog] = useState<Product[]>([]);
  const [isCatalogLoading, setIsCatalogLoading] = useState(false);
  const [catalogReady, setCatalogReady] = useState(false);
  const [recentSearches, setRecentSearches] = useState<string[]>([]);
  const debouncedQuery = useDebouncedValue(query, SEARCH_DEBOUNCE_MS);
  const origin = scopeToOrigin(scope);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    let cancelled = false;
    setIsCatalogLoading(true);

    void productService.list().then((products) => {
      if (cancelled) {
        return;
      }
      clearSearchQueryCache();
      setCatalog(products);
      setCatalogReady(true);
      setIsCatalogLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const refreshRecent = () => setRecentSearches(getRecentSearches());
    refreshRecent();

    window.addEventListener("recent-searches-updated", refreshRecent);
    return () => window.removeEventListener("recent-searches-updated", refreshRecent);
  }, [enabled]);

  const results = useMemo(() => {
    if (!catalogReady) {
      return EMPTY_RESULTS;
    }
    return searchCatalog(catalog, debouncedQuery, { origin });
  }, [catalog, catalogReady, debouncedQuery, origin]);

  const isSearching = enabled && query.trim().length > 0 && debouncedQuery !== query;
  const isLoading = isCatalogLoading && !catalogReady;

  return {
    results,
    recentSearches,
    isLoading,
    isSearching,
    catalogReady,
    debouncedQuery,
    origin,
  };
}
