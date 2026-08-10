import { getApiUrl } from "@/lib/config/env";
import type { ApiCatalogProductCard } from "@/lib/api/products";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";

export type UnifiedSearchSuggestScope = "all" | "china" | "tz";

export type UnifiedSuggestBrand = {
  kind: "catalog_brand";
  id: string;
  slug: string;
  name: string;
  logo?: string | null;
  relevance_score?: number;
};

export type UnifiedSuggestStore = {
  kind: "tz_store";
  id: string;
  slug: string;
  name: string;
  code?: string;
  relevance_score?: number;
};

export type UnifiedSuggestCategory = {
  kind: "category";
  id: string;
  slug: string;
  name: string;
  store_id?: string | null;
  relevance_score?: number;
};

export type UnifiedSuggestProduct = ApiCatalogProductCard & {
  marketplace?: "china" | "tz";
  commerce_channel_code?: string | null;
  matched_on?: string[];
  relevance_score?: number;
  store?: { id: string; slug: string; name: string } | null;
  brand?: { id?: string; slug?: string; name?: string } | null;
};

export type UnifiedSearchSuggestData = {
  q: string;
  scope: UnifiedSearchSuggestScope;
  products: UnifiedSuggestProduct[];
  brands: UnifiedSuggestBrand[];
  stores: UnifiedSuggestStore[];
  categories: UnifiedSuggestCategory[];
};

export class MarketplaceSearchApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "MarketplaceSearchApiError";
  }
}

export function resolveUnifiedSuggestScope(
  scope: SearchMarketplaceScope,
): UnifiedSearchSuggestScope {
  if (scope === "china" || scope === "tz") {
    return scope;
  }
  return "all";
}

export function buildUnifiedSuggestQuery(params: {
  q: string;
  scope: SearchMarketplaceScope;
  limitProducts?: number;
  limitBrands?: number;
  limitStores?: number;
  limitCategories?: number;
}): URLSearchParams {
  const search = new URLSearchParams();
  search.set("q", params.q.trim());
  search.set("scope", resolveUnifiedSuggestScope(params.scope));
  if (params.limitProducts != null) {
    search.set("limit_products", String(params.limitProducts));
  }
  if (params.limitBrands != null) {
    search.set("limit_brands", String(params.limitBrands));
  }
  if (params.limitStores != null) {
    search.set("limit_stores", String(params.limitStores));
  }
  if (params.limitCategories != null) {
    search.set("limit_categories", String(params.limitCategories));
  }
  return search;
}

function isServerRuntime(): boolean {
  return typeof window === "undefined";
}

function buildSuggestUrl(searchParams: URLSearchParams): string {
  const query = searchParams.toString();
  if (isServerRuntime()) {
    const apiUrl = getApiUrl();
    if (!apiUrl) {
      throw new MarketplaceSearchApiError("API URL is not configured.");
    }
    return `${apiUrl}/api/v1/search/suggest${query ? `?${query}` : ""}`;
  }
  return `/api/search/suggest${query ? `?${query}` : ""}`;
}

export async function fetchUnifiedSearchSuggest(params: {
  q: string;
  scope: SearchMarketplaceScope;
  limitProducts?: number;
  limitBrands?: number;
  limitStores?: number;
  limitCategories?: number;
}): Promise<UnifiedSearchSuggestData> {
  const trimmed = params.q.trim();
  if (!trimmed) {
    return {
      q: "",
      scope: resolveUnifiedSuggestScope(params.scope),
      products: [],
      brands: [],
      stores: [],
      categories: [],
    };
  }

  const searchParams = buildUnifiedSuggestQuery({ ...params, q: trimmed });
  const response = await fetch(buildSuggestUrl(searchParams), {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    data?: UnifiedSearchSuggestData;
    message?: string;
  };

  if (!response.ok) {
    throw new MarketplaceSearchApiError(
      payload.message ?? "Unable to load search suggestions.",
      response.status,
    );
  }

  const data = payload.data;
  if (!data) {
    return {
      q: trimmed,
      scope: resolveUnifiedSuggestScope(params.scope),
      products: [],
      brands: [],
      stores: [],
      categories: [],
    };
  }

  return {
    q: data.q ?? trimmed,
    scope: data.scope ?? resolveUnifiedSuggestScope(params.scope),
    products: data.products ?? [],
    brands: data.brands ?? [],
    stores: data.stores ?? [],
    categories: data.categories ?? [],
  };
}
