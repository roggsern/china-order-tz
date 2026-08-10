import { getApiUrl } from "@/lib/config/env";
import type { ApiCatalogProductCard, CatalogPaginationMeta } from "@/lib/api/products";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";
import type { Product } from "@/lib/types/catalog";
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

export type UnifiedSearchProductsMeta = CatalogPaginationMeta & {
  q: string;
  scope: UnifiedSearchSuggestScope;
};

export type UnifiedSearchProductsResult = {
  products: Product[];
  meta: UnifiedSearchProductsMeta;
};

export function buildUnifiedSearchProductsQuery(params: {
  q: string;
  scope?: SearchMarketplaceScope;
  page?: number;
  perPage?: number;
  sort?: "relevance" | "newest";
}): URLSearchParams {
  const search = new URLSearchParams();
  search.set("q", params.q.trim());
  search.set("scope", resolveUnifiedSuggestScope(params.scope ?? "all"));
  if (params.page != null) {
    search.set("page", String(params.page));
  }
  if (params.perPage != null) {
    search.set("per_page", String(params.perPage));
  }
  if (params.sort) {
    search.set("sort", params.sort);
  }
  return search;
}

function buildProductsUrl(searchParams: URLSearchParams): string {
  const query = searchParams.toString();
  if (isServerRuntime()) {
    const apiUrl = getApiUrl();
    if (!apiUrl) {
      throw new MarketplaceSearchApiError("API URL is not configured.");
    }
    return `${apiUrl}/api/v1/search/products${query ? `?${query}` : ""}`;
  }
  return `/api/search/products${query ? `?${query}` : ""}`;
}

function mapUnifiedProductCard(entry: UnifiedSuggestProduct): Product {
  const card = {
    ...entry,
    brand:
      entry.brand && typeof entry.brand === "object"
        ? {
            id: entry.brand.id ?? "",
            name: entry.brand.name ?? "",
            slug: entry.brand.slug ?? "",
          }
        : entry.brand,
  } as ApiCatalogProductCard;

  const product = mapApiProductCardToCatalogProduct(card);

  if (entry.marketplace === "china" || entry.marketplace === "tz") {
    product.origin = entry.marketplace;
  } else if (entry.commerce_channel_code) {
    product.commerceChannelCode = entry.commerce_channel_code;
  }

  if (entry.store?.name && !product.brand) {
    product.brand = entry.store.name;
  }

  return product;
}

export async function fetchUnifiedSearchProducts(params: {
  q: string;
  scope?: SearchMarketplaceScope;
  page?: number;
  perPage?: number;
  sort?: "relevance" | "newest";
}): Promise<UnifiedSearchProductsResult> {
  const scope = resolveUnifiedSuggestScope(params.scope ?? "all");
  const trimmed = params.q.trim();
  const page = Math.max(1, params.page ?? 1);
  const perPage = Math.min(48, Math.max(1, params.perPage ?? 24));

  if (!trimmed) {
    return {
      products: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
        q: "",
        scope,
      },
    };
  }

  const searchParams = buildUnifiedSearchProductsQuery({
    q: trimmed,
    scope,
    page,
    perPage,
    sort: params.sort ?? "relevance",
  });

  const response = await fetch(buildProductsUrl(searchParams), {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    data?: UnifiedSuggestProduct[];
    meta?: Partial<UnifiedSearchProductsMeta>;
    message?: string;
  };

  if (!response.ok) {
    throw new MarketplaceSearchApiError(
      payload.message ?? "Unable to load search results.",
      response.status,
    );
  }

  const products = (payload.data ?? []).map(mapUnifiedProductCard);

  return {
    products,
    meta: {
      current_page: payload.meta?.current_page ?? page,
      last_page: payload.meta?.last_page ?? 1,
      per_page: payload.meta?.per_page ?? perPage,
      total: payload.meta?.total ?? products.length,
      q: payload.meta?.q ?? trimmed,
      scope: payload.meta?.scope ?? scope,
    },
  };
}
