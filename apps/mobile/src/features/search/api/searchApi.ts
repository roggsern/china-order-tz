import { apiClient } from '@/src/core/api';
import type { SearchResponse, SearchSort, SearchSuggestionsResult } from '../models/types';
import type { SearchScope } from '../utils/journeyScope';
import { mapSearchProductsResponse, mapSearchSuggestions } from '../utils/mapSearch';

export type FetchSearchSuggestionsParams = {
  q: string;
  scope: SearchScope;
  limitProducts?: number;
  limitBrands?: number;
  limitStores?: number;
  limitCategories?: number;
};

export type FetchSearchProductsParams = {
  q: string;
  scope: SearchScope;
  page?: number;
  perPage?: number;
  sort?: SearchSort;
};

/**
 * GET /search/suggest — public Contract v1.
 */
export async function fetchSearchSuggestions(
  params: FetchSearchSuggestionsParams,
): Promise<SearchSuggestionsResult> {
  const response = await apiClient.get<unknown>(
    '/search/suggest',
    {
      q: params.q,
      scope: params.scope,
      limit_products: params.limitProducts,
      limit_brands: params.limitBrands,
      limit_stores: params.limitStores,
      limit_categories: params.limitCategories,
    },
    null,
  );

  return mapSearchSuggestions(response);
}

/**
 * GET /search/products — public Contract v1.
 */
export async function fetchSearchProducts(
  params: FetchSearchProductsParams,
): Promise<SearchResponse> {
  const response = await apiClient.get<unknown>(
    '/search/products',
    {
      q: params.q,
      scope: params.scope,
      page: params.page ?? 1,
      per_page: params.perPage ?? 24,
      sort: params.sort ?? 'relevance',
    },
    null,
  );

  return mapSearchProductsResponse(response);
}
