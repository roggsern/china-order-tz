import {
  keepPreviousData,
  useInfiniteQuery,
  useQuery,
} from '@tanstack/react-query';
import {
  fetchChinaCategories,
  fetchChinaProducts,
  fetchProductConfiguration,
  fetchProductDetail,
  fetchProductQuote,
  fetchTzCategories,
  fetchTzProducts,
  fetchTzStores,
  type ListProductsQuery,
} from '../api/catalogApi';
import type {
  CatalogProductCard,
  ConfigurationSelections,
  ProductDetailParams,
  ProductListResult,
} from '../models/types';

export function chinaCategoriesQueryKey() {
  return ['catalog', 'china', 'categories'] as const;
}

export function chinaProductsQueryKey(query: ListProductsQuery) {
  return ['catalog', 'china', 'products', query] as const;
}

export function chinaProductsInfiniteQueryKey(params: {
  category?: string | null;
  perPage: number;
}) {
  return [
    'catalog',
    'china',
    'products',
    'infinite',
    params.category ?? null,
    params.perPage,
  ] as const;
}

export function tzStoresQueryKey() {
  return ['catalog', 'tz', 'stores'] as const;
}

export function tzCategoriesQueryKey(storeSlug: string) {
  return ['catalog', 'tz', 'categories', storeSlug] as const;
}

export function tzProductsQueryKey(storeSlug: string, query: ListProductsQuery) {
  return ['catalog', 'tz', 'products', storeSlug, query] as const;
}

export function tzProductsInfiniteQueryKey(params: {
  storeSlug: string;
  category?: string | null;
  perPage: number;
}) {
  return [
    'catalog',
    'tz',
    'products',
    'infinite',
    params.storeSlug,
    params.category ?? null,
    params.perPage,
  ] as const;
}

export function productDetailQueryKey(params: ProductDetailParams) {
  return [
    'catalog',
    'product',
    params.journey,
    params.storeSlug ?? null,
    params.productKey,
  ] as const;
}

export function productConfigurationQueryKey(
  productKey: string,
  selections: ConfigurationSelections = {},
) {
  const selectionEntries = Object.entries(selections).sort(([a], [b]) =>
    a.localeCompare(b),
  );
  return ['catalog', 'configuration', productKey, selectionEntries] as const;
}

export function productQuoteQueryKey(params: {
  productKey: string;
  configurationId: string;
  quantity: number;
}) {
  return [
    'catalog',
    'quote',
    params.productKey,
    params.configurationId,
    params.quantity,
  ] as const;
}

/** Merge infinite catalog pages without inventing products. */
export function flattenCatalogProductPages(
  pages: ProductListResult[] | undefined,
): CatalogProductCard[] {
  if (!pages?.length) return [];
  const seen = new Set<string>();
  const products: CatalogProductCard[] = [];
  for (const page of pages) {
    for (const product of page.products) {
      if (seen.has(product.id)) continue;
      seen.add(product.id);
      products.push(product);
    }
  }
  return products;
}

export function getNextCatalogPageParam(
  lastPage: ProductListResult,
): number | undefined {
  if (lastPage.lastPage == null || lastPage.lastPage < 1) {
    return undefined;
  }
  return lastPage.page < lastPage.lastPage ? lastPage.page + 1 : undefined;
}

export function useChinaCategories() {
  return useQuery({
    queryKey: chinaCategoriesQueryKey(),
    queryFn: fetchChinaCategories,
  });
}

/** @deprecated Prefer useChinaProductsInfinite for browse pagination. */
export function useChinaProducts(query: ListProductsQuery) {
  return useQuery({
    queryKey: chinaProductsQueryKey(query),
    queryFn: () => fetchChinaProducts(query),
  });
}

export function useChinaProductsInfinite(options: {
  category?: string | null;
  perPage?: number;
}) {
  const perPage = options.perPage ?? 24;
  return useInfiniteQuery({
    queryKey: chinaProductsInfiniteQueryKey({
      category: options.category,
      perPage,
    }),
    queryFn: ({ pageParam }) =>
      fetchChinaProducts({
        category: options.category,
        page: pageParam,
        perPage,
      }),
    initialPageParam: 1,
    getNextPageParam: getNextCatalogPageParam,
  });
}

export function useTzStores() {
  return useQuery({
    queryKey: tzStoresQueryKey(),
    queryFn: fetchTzStores,
  });
}

export function useTzCategories(storeSlug: string | null) {
  return useQuery({
    queryKey: tzCategoriesQueryKey(storeSlug ?? ''),
    queryFn: () => fetchTzCategories(storeSlug!),
    enabled: Boolean(storeSlug),
  });
}

/** @deprecated Prefer useTzProductsInfinite for browse pagination. */
export function useTzProducts(storeSlug: string | null, query: ListProductsQuery) {
  return useQuery({
    queryKey: tzProductsQueryKey(storeSlug ?? '', query),
    queryFn: () => fetchTzProducts(storeSlug!, query),
    enabled: Boolean(storeSlug),
  });
}

export function useTzProductsInfinite(options: {
  storeSlug: string | null;
  category?: string | null;
  perPage?: number;
}) {
  const perPage = options.perPage ?? 24;
  const storeSlug = options.storeSlug;
  return useInfiniteQuery({
    queryKey: tzProductsInfiniteQueryKey({
      storeSlug: storeSlug ?? '',
      category: options.category,
      perPage,
    }),
    queryFn: ({ pageParam }) =>
      fetchTzProducts(storeSlug!, {
        category: options.category,
        page: pageParam,
        perPage,
      }),
    initialPageParam: 1,
    getNextPageParam: getNextCatalogPageParam,
    enabled: Boolean(storeSlug),
  });
}

export function useProductDetail(params: ProductDetailParams | null) {
  return useQuery({
    queryKey: params
      ? productDetailQueryKey(params)
      : (['catalog', 'product', 'idle'] as const),
    queryFn: () => fetchProductDetail(params!),
    enabled: Boolean(params?.productKey) &&
      (params?.journey !== 'TZ_LOCAL' || Boolean(params?.storeSlug)),
  });
}

export function useProductConfiguration(
  productKey: string | null,
  selections: ConfigurationSelections = {},
  enabled = true,
) {
  return useQuery({
    queryKey: productConfigurationQueryKey(productKey ?? '', selections),
    queryFn: () => fetchProductConfiguration(productKey!, selections),
    enabled: Boolean(productKey) && enabled,
    placeholderData: keepPreviousData,
  });
}

/**
 * Authoritative unit price for a matched configuration via POST quote.
 * Disabled while configuration is incomplete/unmatched.
 */
export function useProductQuote(params: {
  productKey: string | null;
  configurationId: string | null;
  quantity: number;
  enabled?: boolean;
}) {
  const enabled =
    (params.enabled ?? true) &&
    Boolean(params.productKey) &&
    Boolean(params.configurationId) &&
    Number.isFinite(params.quantity) &&
    params.quantity >= 1;

  return useQuery({
    queryKey: productQuoteQueryKey({
      productKey: params.productKey ?? '',
      configurationId: params.configurationId ?? '',
      quantity: params.quantity,
    }),
    queryFn: () =>
      fetchProductQuote({
        productKey: params.productKey!,
        configurationId: params.configurationId!,
        quantity: params.quantity,
      }),
    enabled,
  });
}
