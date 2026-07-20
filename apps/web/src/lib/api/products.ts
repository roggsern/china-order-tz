import { getApiUrl } from "@/lib/config/env";

export type ApiCatalogCategory = {
  id: string;
  parent_id?: string | null;
  origin?: "china" | "tz" | null;
  name: string;
  slug: string;
  sort_order?: number;
  children?: ApiCatalogCategory[];
};

export type ApiCatalogBrand = {
  id: string;
  name: string;
  slug: string;
};

export type ApiCatalogImage = {
  id: string;
  path: string;
  url?: string | null;
  alt_text: string | null;
};

export type ApiCatalogProductCard = {
  id: string;
  slug: string;
  name: string;
  short_description: string | null;
  price: string | number;
  compare_at_price: string | number | null;
  is_featured: boolean;
  primary_image: ApiCatalogImage | null;
  category: ApiCatalogCategory | null;
  brand: ApiCatalogBrand | null;
  average_rating: number;
  review_count: number;
  shipping_prices?: {
    air: string | number | null;
    sea: string | number | null;
  };
  requires_china_shipping?: boolean;
  commerce_channel_code?: string | null;
  commerce_source_label?: string | null;
};

export type ApiCatalogProductVariant = {
  id: string;
  sku: string | null;
  name: string;
  price: string | number | null;
  compare_at_price: string | number | null;
  weight: string | number | null;
  effective_price?: string | number;
  attribute_values?: Array<{
    id: string;
    value: string;
    slug: string;
    color_code?: string | null;
    sort_order?: number;
    attribute?: {
      id: string;
      name: string;
      slug: string;
    };
  }>;
};

export type ApiCatalogProductDetail = ApiCatalogProductCard & {
  description: string | null;
  weight: string | number | null;
  dimensions: string | null;
  images: ApiCatalogImage[];
  variants: ApiCatalogProductVariant[];
  shipping_prices: {
    air: string | number | null;
    sea: string | number | null;
  };
  requires_china_shipping?: boolean;
  commerce_channel?: {
    id: string;
    code: string;
    name: string;
    customer_label?: string | null;
  } | null;
  commerce_source_label?: string | null;
};

export type CatalogPaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type CatalogListResponse<T> = {
  success: boolean;
  data: T[];
  meta?: CatalogPaginationMeta;
  links?: Record<string, string | null>;
  message?: string;
};

export type CatalogItemResponse<T> = {
  success: boolean;
  data: T;
  message?: string;
};

export type ProductListParams = {
  page?: number;
  per_page?: number;
  featured?: boolean;
  category?: string;
  brand?: string;
  store?: string;
  origin?: "china" | "tz";
  commerce_channel?: string;
  search?: string;
};

export class CatalogApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CatalogApiError";
  }
}

export type ProductListResult = {
  products: ApiCatalogProductCard[];
  meta: CatalogPaginationMeta;
};

function isServerRuntime(): boolean {
  return typeof window === "undefined";
}

function buildCatalogUrl(path: string, searchParams?: URLSearchParams): string {
  const query = searchParams?.toString();

  if (isServerRuntime()) {
    const apiUrl = getApiUrl();

    if (!apiUrl) {
      throw new CatalogApiError("API URL is not configured.");
    }

    return `${apiUrl}/api/v1${path}${query ? `?${query}` : ""}`;
  }

  return `/api/catalog${path}${query ? `?${query}` : ""}`;
}

async function fetchCatalogJson<T>(path: string, searchParams?: URLSearchParams): Promise<T> {
  const response = await fetch(buildCatalogUrl(path, searchParams), {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as T & { message?: string };

  if (!response.ok) {
    throw new CatalogApiError(payload.message ?? "Unable to load catalog data.", response.status);
  }

  return payload;
}

function buildProductSearchParams(params?: ProductListParams): URLSearchParams {
  const searchParams = new URLSearchParams();

  if (params?.page) {
    searchParams.set("page", String(params.page));
  }

  if (params?.per_page) {
    searchParams.set("per_page", String(params.per_page));
  }

  if (params?.featured) {
    searchParams.set("featured", "1");
  }

  if (params?.category?.trim()) {
    searchParams.set("category", params.category.trim());
  }

  if (params?.brand?.trim()) {
    searchParams.set("brand", params.brand.trim());
  }

  if (params?.store?.trim()) {
    searchParams.set("store", params.store.trim());
  }

  if (params?.origin?.trim()) {
    searchParams.set("origin", params.origin.trim());
  }

  if (params?.commerce_channel?.trim()) {
    searchParams.set("commerce_channel", params.commerce_channel.trim());
  }

  if (params?.search?.trim()) {
    searchParams.set("search", params.search.trim());
  }

  return searchParams;
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

export async function getProducts(params?: ProductListParams): Promise<ProductListResult> {
  const payload = await fetchCatalogJson<CatalogListResponse<ApiCatalogProductCard>>(
    "/products",
    buildProductSearchParams(params),
  );

  return {
    products: payload.data ?? [],
    meta: normalizePaginationMeta(payload.meta, payload.data?.length ?? 0),
  };
}

export async function getFeaturedProducts(limit = 8): Promise<ApiCatalogProductCard[]> {
  const result = await getProducts({
    featured: true,
    per_page: limit,
    page: 1,
  });

  return result.products.slice(0, limit);
}

export async function getProduct(slug: string): Promise<ApiCatalogProductDetail> {
  const trimmedSlug = slug.trim();

  if (!trimmedSlug) {
    throw new CatalogApiError("Product slug is required.", 422);
  }

  const payload = await fetchCatalogJson<CatalogItemResponse<ApiCatalogProductDetail>>(
    `/products/${encodeURIComponent(trimmedSlug)}`,
  );

  if (!payload.data) {
    throw new CatalogApiError("Product not found.", 404);
  }

  return payload.data;
}

export async function getCategories(params?: {
  origin?: "china" | "tz";
  tree?: boolean;
  withProducts?: boolean;
}): Promise<ApiCatalogCategory[]> {
  const search = new URLSearchParams();
  if (params?.origin) search.set("origin", params.origin);
  if (params?.tree === false) search.set("tree", "0");
  if (params?.withProducts) search.set("with_products", "1");
  const query = search.toString();
  const payload = await fetchCatalogJson<CatalogListResponse<ApiCatalogCategory>>(
    `/categories${query ? `?${query}` : ""}`,
  );
  return payload.data ?? [];
}

export async function getBrands(params?: {
  categoryId?: string;
  withProducts?: boolean;
}): Promise<ApiCatalogBrand[]> {
  const search = new URLSearchParams();
  if (params?.categoryId) search.set("category_id", params.categoryId);
  if (params?.withProducts === false) search.set("with_products", "0");
  const query = search.toString();
  const payload = await fetchCatalogJson<CatalogListResponse<ApiCatalogBrand>>(
    `/brands${query ? `?${query}` : ""}`,
  );
  return payload.data ?? [];
}
