import { useInfiniteQuery } from '@tanstack/react-query';
import { fetchSearchProducts } from '../api/searchApi';
import type { SearchSort } from '../models/types';
import type { SearchScope } from '../utils/journeyScope';
import { resolveSearchScope, shouldFetchSearch } from '../utils/journeyScope';

export function searchProductsQueryKey(params: {
  q: string;
  scope: SearchScope | string;
  sort: SearchSort;
  perPage: number;
}) {
  return [
    'search',
    'products',
    params.scope,
    params.q,
    params.sort,
    params.perPage,
  ] as const;
}

/** Build the exact query params sent to GET /search/products. */
export function buildSearchProductsRequestParams(params: {
  q: string;
  scope?: SearchScope | null;
  page?: number;
  perPage?: number;
  sort?: SearchSort;
}): {
  q: string;
  scope: SearchScope;
  page: number;
  per_page: number;
  sort: SearchSort;
} {
  return {
    q: params.q.trim(),
    scope: resolveSearchScope(params.scope),
    page: params.page ?? 1,
    per_page: params.perPage ?? 24,
    sort: params.sort ?? 'relevance',
  };
}

export type UseSearchProductsOptions = {
  q: string;
  /** Explicit marketplace scope — defaults to web-parity `all`. */
  scope?: SearchScope;
  sort?: SearchSort;
  perPage?: number;
  enabled?: boolean;
};

export function useSearchProducts(options: UseSearchProductsOptions) {
  const scope = resolveSearchScope(options.scope);
  const trimmed = options.q.trim();
  const sort = options.sort ?? 'relevance';
  const perPage = options.perPage ?? 24;
  const enabled =
    (options.enabled ?? true) && shouldFetchSearch(trimmed);

  return useInfiniteQuery({
    queryKey: searchProductsQueryKey({ q: trimmed, scope, sort, perPage }),
    queryFn: ({ pageParam }) =>
      fetchSearchProducts({
        q: trimmed,
        scope,
        page: pageParam,
        perPage,
        sort,
      }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.page < lastPage.lastPage ? lastPage.page + 1 : undefined,
    enabled,
  });
}
