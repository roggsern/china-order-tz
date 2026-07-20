import { getApiUrl } from "@/lib/config/env";
import type {
  ApiCatalogBrand,
  ApiCatalogCategory,
  ApiCatalogProductCard,
} from "@/lib/api/products";

export type ChinaStorefrontMenu = {
  label: string;
  categories: ApiCatalogCategory[];
  active_category: string | null;
  brands: ApiCatalogBrand[];
  featured_products: ApiCatalogProductCard[];
};

export class ChinaStorefrontApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "ChinaStorefrontApiError";
  }
}

function isServerRuntime(): boolean {
  return typeof window === "undefined";
}

function buildUrl(path: string, searchParams?: URLSearchParams): string {
  const query = searchParams?.toString();
  if (isServerRuntime()) {
    const apiUrl = getApiUrl();
    if (!apiUrl) throw new ChinaStorefrontApiError("API URL is not configured.");
    return `${apiUrl}/api/v1/storefront/china${path}${query ? `?${query}` : ""}`;
  }
  return `/api/storefront/china${path}${query ? `?${query}` : ""}`;
}

async function fetchJson<T>(path: string, searchParams?: URLSearchParams): Promise<T> {
  const response = await fetch(buildUrl(path, searchParams), {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = (await response.json()) as T & { message?: string };
  if (!response.ok) {
    throw new ChinaStorefrontApiError(
      payload.message ?? "Unable to load ORDER FROM CHINA data.",
      response.status,
    );
  }
  return payload;
}

export async function getChinaStorefrontMenu(category?: string): Promise<ChinaStorefrontMenu> {
  const search = new URLSearchParams();
  if (category?.trim()) search.set("category", category.trim());
  const payload = await fetchJson<{ data?: ChinaStorefrontMenu }>("/menu", search);
  if (!payload.data) {
    throw new ChinaStorefrontApiError("ORDER FROM CHINA menu is empty.", 404);
  }
  return payload.data;
}

export async function getChinaStorefrontCategories(): Promise<ApiCatalogCategory[]> {
  const payload = await fetchJson<{ data?: ApiCatalogCategory[] }>("/categories");
  return payload.data ?? [];
}

export async function getChinaStorefrontBrands(category?: string): Promise<ApiCatalogBrand[]> {
  const search = new URLSearchParams();
  if (category?.trim()) search.set("category", category.trim());
  const payload = await fetchJson<{ data?: ApiCatalogBrand[] }>("/brands", search);
  return payload.data ?? [];
}

export async function getChinaStorefrontProducts(params?: {
  category?: string;
  brand?: string;
  featured?: boolean;
  per_page?: number;
}): Promise<ApiCatalogProductCard[]> {
  const search = new URLSearchParams();
  if (params?.category) search.set("category", params.category);
  if (params?.brand) search.set("brand", params.brand);
  if (params?.featured) search.set("featured", "1");
  if (params?.per_page) search.set("per_page", String(params.per_page));
  const payload = await fetchJson<{ data?: ApiCatalogProductCard[] }>("/products", search);
  return payload.data ?? [];
}
