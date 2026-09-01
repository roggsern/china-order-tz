import { apiClient } from '@/src/core/api';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import {
  chinaCategoriesPath,
  chinaProductsPath,
  resolveProductDetailPath,
  sharedProductConfigurationPath,
  sharedProductQuotePath,
  tzStoreCategoriesPath,
  tzStoreProductsPath,
  tzStoresPath,
} from '../map/journeyRoutes';
import {
  buildConfigurationQuery,
  mapCategoryList,
  mapProductConfiguration,
  mapProductDetail,
  mapProductListResponse,
  mapProductQuote,
  mapStoreList,
} from '../map/mapProduct';
import type {
  CatalogCategory,
  CatalogProductDetail,
  CatalogStore,
  ConfigurationSelections,
  ProductConfiguration,
  ProductDetailParams,
  ProductListResult,
  ProductQuote,
} from '../models/types';
import { ApiError } from '@/src/core/errors';

export type ListProductsQuery = {
  category?: string | null;
  brand?: string | null;
  search?: string | null;
  featured?: boolean;
  page?: number;
  perPage?: number;
};

export async function fetchChinaCategories(): Promise<CatalogCategory[]> {
  const response = await apiClient.get<unknown>(chinaCategoriesPath(), undefined, null);
  return mapCategoryList(response.data);
}

export async function fetchChinaProducts(
  query: ListProductsQuery = {},
): Promise<ProductListResult> {
  const response = await apiClient.get<unknown>(
    chinaProductsPath(),
    {
      category: query.category ?? undefined,
      brand: query.brand ?? undefined,
      search: query.search ?? undefined,
      featured: query.featured ? 1 : undefined,
      page: query.page ?? 1,
      per_page: query.perPage ?? 24,
    },
    null,
  );
  return mapProductListResponse(response);
}

export async function fetchTzStores(): Promise<CatalogStore[]> {
  const response = await apiClient.get<unknown>(tzStoresPath(), undefined, null);
  return mapStoreList(response.data);
}

export async function fetchTzCategories(storeSlug: string): Promise<CatalogCategory[]> {
  const response = await apiClient.get<unknown>(
    tzStoreCategoriesPath(storeSlug),
    undefined,
    null,
  );
  return mapCategoryList(response.data);
}

export async function fetchTzProducts(
  storeSlug: string,
  query: ListProductsQuery = {},
): Promise<ProductListResult> {
  const response = await apiClient.get<unknown>(
    tzStoreProductsPath(storeSlug),
    {
      category: query.category ?? undefined,
      search: query.search ?? undefined,
      page: query.page ?? 1,
      per_page: query.perPage ?? 24,
    },
    null,
  );
  return mapProductListResponse({
    data: response.data,
    meta: response.meta,
    store: (response as { store?: unknown }).store,
  });
}

export async function fetchProductDetail(
  params: ProductDetailParams,
): Promise<CatalogProductDetail> {
  const path = resolveProductDetailPath(params);
  const response = await apiClient.get<unknown>(path, undefined, null);
  const product = mapProductDetail(response.data);
  if (!product) {
    throw new ApiError({
      message: 'Unexpected product detail response',
      status: 500,
      code: 'server_error',
    });
  }
  return product;
}

export async function fetchProductConfiguration(
  productKey: string,
  selections: ConfigurationSelections = {},
): Promise<ProductConfiguration> {
  const response = await apiClient.get<unknown>(
    sharedProductConfigurationPath(productKey),
    buildConfigurationQuery(selections),
    null,
  );
  return mapProductConfiguration(response.data);
}

/** POST /products/{slug}/quote — server pricing pipeline for matched configuration. */
export async function fetchProductQuote(params: {
  productKey: string;
  configurationId: string | null;
  quantity: number;
}): Promise<ProductQuote> {
  const response = await apiClient.post<unknown>(
    sharedProductQuotePath(params.productKey),
    {
      configuration_id: params.configurationId,
      quantity: params.quantity,
    },
    null,
  );
  const quote = mapProductQuote(response.data);
  if (!quote) {
    throw new ApiError({
      message: 'Unexpected product quote response',
      status: 500,
      code: 'server_error',
    });
  }
  return quote;
}

export async function fetchCatalogProductsForJourney(params: {
  journey: CommerceJourney;
  storeSlug?: string | null;
  query?: ListProductsQuery;
}): Promise<ProductListResult> {
  if (params.journey === 'TZ_LOCAL') {
    if (!params.storeSlug) {
      return { products: [], page: 1, lastPage: null, total: 0, store: null };
    }
    return fetchTzProducts(params.storeSlug, params.query);
  }
  return fetchChinaProducts(params.query);
}
