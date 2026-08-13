import { getApiUrl } from "@/lib/config/env";
import type {
  ApiCatalogBrand,
  ApiCatalogCategory,
  ApiCatalogProductCard,
  CatalogPaginationMeta,
  ProductListResult,
} from "@/lib/api/products";

/** Wave 2 slim mega-menu featured tile — not a full listing card. */
export type ChinaMegaMenuFeaturedProduct = {
  id: string;
  slug: string;
  name: string;
  primary_image?: {
    id?: string | null;
    path?: string | null;
    url?: string | null;
    alt_text?: string | null;
  } | null;
  brand?: Pick<ApiCatalogBrand, "id" | "name" | "slug"> | null;
};

export type ChinaStorefrontMenu = {
  label: string;
  categories: ApiCatalogCategory[];
  active_category: string | null;
  /** Mega-menu brand chips — identity fields required; extra brand fields optional. */
  brands: Array<Pick<ApiCatalogBrand, "id" | "name" | "slug"> & Partial<ApiCatalogBrand>>;
  /** Slim featured tiles (id/slug/name/primary_image/brand) — no listing-card payload. */
  featured_products: ChinaMegaMenuFeaturedProduct[];
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
  const search = searchParams?.get("search")?.trim() ?? "";
  const canRevalidate = search === "";

  const response = await fetch(buildUrl(path, searchParams), {
    headers: { Accept: "application/json" },
    ...(canRevalidate
      ? { next: { revalidate: 120 } }
      : { cache: "no-store" as RequestCache }),
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

export async function getChinaStorefrontFeaturedCollections(): Promise<ApiCatalogCategory[]> {
  const payload = await fetchJson<{ data?: ApiCatalogCategory[] }>("/featured-collections");
  return payload.data ?? [];
}

export async function getChinaStorefrontBrands(category?: string): Promise<ApiCatalogBrand[]> {
  const search = new URLSearchParams();
  if (category?.trim()) search.set("category", category.trim());
  const payload = await fetchJson<{ data?: ApiCatalogBrand[] }>("/brands", search);
  return payload.data ?? [];
}

function normalizePaginationMeta(
  meta: CatalogPaginationMeta | undefined,
  dataLength: number,
): CatalogPaginationMeta {
  return {
    current_page: meta?.current_page ?? 1,
    last_page: meta?.last_page ?? 1,
    per_page: meta?.per_page ?? dataLength,
    total: meta?.total ?? dataLength,
  };
}

export async function getChinaStorefrontProductsPage(params?: {
  category?: string;
  brand?: string;
  featured?: boolean;
  search?: string;
  per_page?: number;
  page?: number;
}): Promise<ProductListResult> {
  const search = new URLSearchParams();
  if (params?.category) search.set("category", params.category);
  if (params?.brand) search.set("brand", params.brand);
  if (params?.featured) search.set("featured", "1");
  if (params?.search?.trim()) search.set("search", params.search.trim());
  if (params?.per_page) search.set("per_page", String(params.per_page));
  if (params?.page) search.set("page", String(params.page));
  const payload = await fetchJson<{
    data?: ApiCatalogProductCard[];
    meta?: CatalogPaginationMeta;
  }>("/products", search);

  const products = payload.data ?? [];

  return {
    products,
    meta: normalizePaginationMeta(payload.meta, products.length),
  };
}

export async function getChinaStorefrontProducts(params?: {
  category?: string;
  brand?: string;
  featured?: boolean;
  search?: string;
  per_page?: number;
  page?: number;
}): Promise<ApiCatalogProductCard[]> {
  const result = await getChinaStorefrontProductsPage(params);
  return result.products;
}
