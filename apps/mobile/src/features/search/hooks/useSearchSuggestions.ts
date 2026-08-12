import { useQuery } from '@tanstack/react-query';
import { fetchSearchSuggestions } from '../api/searchApi';
import type { SearchScope } from '../utils/journeyScope';
import { resolveSearchScope, shouldFetchSearch } from '../utils/journeyScope';

export function searchSuggestionsQueryKey(params: {
  q: string;
  scope: string;
}) {
  return ['search', 'suggest', params.scope, params.q] as const;
}

export function useSearchSuggestions(
  q: string,
  scope?: SearchScope | null,
) {
  const resolvedScope = resolveSearchScope(scope);
  const trimmed = q.trim();

  return useQuery({
    queryKey: searchSuggestionsQueryKey({ q: trimmed, scope: resolvedScope }),
    queryFn: () =>
      fetchSearchSuggestions({ q: trimmed, scope: resolvedScope }),
    enabled: shouldFetchSearch(trimmed),
  });
}
