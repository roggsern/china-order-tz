import { useInfiniteQuery } from '@tanstack/react-query';
import { useJourneyStore } from '@/src/core/auth';
import { fetchSearchProducts } from '../api/searchApi';
import type { SearchSort } from '../models/types';
import { journeyToSearchScope, shouldFetchSearch } from '../utils/journeyScope';

export function searchProductsQueryKey(params: {
  q: string;
  scope: string;
  sort: SearchSort;
  perPage: number;
}) {
  return ['search', 'products', params.scope, params.q, params.sort, params.perPage] as const;
}

export type UseSearchProductsOptions = {
  q: string;
  sort?: SearchSort;
  perPage?: number;
  enabled?: boolean;
};

export function useSearchProducts(options: UseSearchProductsOptions) {
  const journey = useJourneyStore((s) => s.journey);
  const scope = journeyToSearchScope(journey);
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
