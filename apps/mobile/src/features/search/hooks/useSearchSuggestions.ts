import { useQuery } from '@tanstack/react-query';
import { useJourneyStore } from '@/src/core/auth';
import { fetchSearchSuggestions } from '../api/searchApi';
import { journeyToSearchScope, shouldFetchSearch } from '../utils/journeyScope';

export function searchSuggestionsQueryKey(params: {
  q: string;
  scope: string;
}) {
  return ['search', 'suggest', params.scope, params.q] as const;
}

export function useSearchSuggestions(q: string) {
  const journey = useJourneyStore((s) => s.journey);
  const scope = journeyToSearchScope(journey);
  const trimmed = q.trim();

  return useQuery({
    queryKey: searchSuggestionsQueryKey({ q: trimmed, scope }),
    queryFn: () => fetchSearchSuggestions({ q: trimmed, scope }),
    enabled: shouldFetchSearch(trimmed),
  });
}
