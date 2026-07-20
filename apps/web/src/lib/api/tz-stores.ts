import { getApiUrl } from "@/lib/config/env";
import type { ApiCatalogCategory, ApiCatalogProductCard, ApiCatalogProductDetail, CatalogPaginationMeta } from "@/lib/api/products";

export type TzStorefrontStore = {
  id: string;
  code: string;
  name: string;
  slug: string;
  description?: string | null;
  logo_path?: string | null;
  logo_url?: string | null;
  banner_path?: string | null;
  banner_url?: string | null;
  theme_color?: string | null;
  storefront_featured?: boolean;
  categories?: ApiCatalogCategory[];
};

export class TzStorefrontApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "TzStorefrontApiError";
  }
}

function isServerRuntime(): boolean {
  return typeof window === "undefined";
}

function buildUrl(path: string, searchParams?: URLSearchParams): string {
  const query = searchParams?.toString();
  if (isServerRuntime()) {
    const apiUrl = getApiUrl();
    if (!apiUrl) throw new TzStorefrontApiError("API URL is not configured.");
    return `${apiUrl}/api/v1/storefront/tz${path}${query ? `?${query}` : ""}`;
  }
  return `/api/storefront/tz${path}${query ? `?${query}` : ""}`;
}

async function fetchJson<T>(path: string, searchParams?: URLSearchParams): Promise<T> {
  const response = await fetch(buildUrl(path, searchParams), {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = (await response.json()) as T & { message?: string; success?: boolean };
  if (!response.ok) {
    throw new TzStorefrontApiError(payload.message ?? "Unable to load BUY FROM TZ data.", response.status);
  }
  return payload;
}

export async function getTzStores(): Promise<TzStorefrontStore[]> {
  const payload = await fetchJson<{ data?: TzStorefrontStore[] }>("/stores");
  return payload.data ?? [];
}

export async function getTzStore(slug: string): Promise<TzStorefrontStore> {
  const payload = await fetchJson<{ data?: TzStorefrontStore }>(`/stores/${encodeURIComponent(slug)}`);
  if (!payload.data) throw new TzStorefrontApiError("Store not found.", 404);
  return payload.data;
}

export async function getTzStoreCategories(storeSlug: string): Promise<ApiCatalogCategory[]> {
  const payload = await fetchJson<{ data?: ApiCatalogCategory[] }>(
    `/stores/${encodeURIComponent(storeSlug)}/categories`,
  );
  return payload.data ?? [];
}

export async function getTzStoreProducts(
  storeSlug: string,
  params?: { category?: string; search?: string; page?: number; per_page?: number },
): Promise<{ products: ApiCatalogProductCard[]; meta: CatalogPaginationMeta }> {
  const search = new URLSearchParams();
  if (params?.category) search.set("category", params.category);
  if (params?.search) search.set("search", params.search);
  if (params?.page) search.set("page", String(params.page));
  if (params?.per_page) search.set("per_page", String(params.per_page));

  const payload = await fetchJson<{
    data?: ApiCatalogProductCard[];
    meta?: CatalogPaginationMeta;
  }>(`/stores/${encodeURIComponent(storeSlug)}/products`, search);

  const products = payload.data ?? [];
  return {
    products,
    meta: {
      current_page: payload.meta?.current_page ?? 1,
      last_page: payload.meta?.last_page ?? 1,
      per_page: payload.meta?.per_page ?? products.length,
      total: payload.meta?.total ?? products.length,
    },
  };
}

export async function getTzStoreProduct(
  storeSlug: string,
  productSlug: string,
): Promise<ApiCatalogProductDetail> {
  const payload = await fetchJson<{ data?: ApiCatalogProductDetail }>(
    `/stores/${encodeURIComponent(storeSlug)}/products/${encodeURIComponent(productSlug)}`,
  );
  if (!payload.data) throw new TzStorefrontApiError("Product not found.", 404);
  return payload.data;
}
