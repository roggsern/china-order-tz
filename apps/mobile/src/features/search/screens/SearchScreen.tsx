import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useJourneyStore } from '@/src/core/auth';
import { SearchInput } from '../components/SearchInput';
import { SearchResultCard } from '../components/SearchResultCard';
import { SuggestionList } from '../components/SuggestionList';
import { useSearchProducts } from '../hooks/useSearchProducts';
import { useSearchSuggestions } from '../hooks/useSearchSuggestions';
import type { SearchSuggestion } from '../models/types';
import { journeySearchLabel, shouldFetchSearch } from '../utils/journeyScope';
import { getSearchErrorMessage } from '../utils/searchErrorMessage';

type Mode = 'suggest' | 'results';

const DEBOUNCE_MS = 280;

export function SearchScreen() {
  const journey = useJourneyStore((s) => s.journey);
  const [input, setInput] = useState('');
  const [debouncedInput, setDebouncedInput] = useState('');
  const [submittedQuery, setSubmittedQuery] = useState('');
  const [mode, setMode] = useState<Mode>('suggest');

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedInput(input.trim()), DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [input]);

  const suggestionsQuery = useSearchSuggestions(
    mode === 'suggest' ? debouncedInput : '',
  );
  const productsQuery = useSearchProducts({
    q: submittedQuery,
    enabled: mode === 'results',
  });

  const hits = useMemo(
    () => productsQuery.data?.pages.flatMap((page) => page.hits) ?? [],
    [productsQuery.data?.pages],
  );

  const total = productsQuery.data?.pages[0]?.total ?? 0;

  function runSearch(query: string) {
    const trimmed = query.trim();
    if (!shouldFetchSearch(trimmed)) {
      setSubmittedQuery('');
      setMode('suggest');
      return;
    }
    setInput(trimmed);
    setDebouncedInput(trimmed);
    setSubmittedQuery(trimmed);
    setMode('results');
  }

  function handleChangeText(value: string) {
    setInput(value);
    if (mode === 'results') {
      setMode('suggest');
      setSubmittedQuery('');
    }
  }

  function handleSelectSuggestion(suggestion: SearchSuggestion) {
    runSearch(suggestion.query);
  }

  return (
    <View style={styles.screen}>
      <Text style={styles.context}>{journeySearchLabel(journey)}</Text>

      <SearchInput
        value={input}
        onChangeText={handleChangeText}
        onSubmit={() => runSearch(input)}
        autoFocus
      />

      {mode === 'suggest' ? (
        <ScrollView
          style={styles.body}
          contentContainerStyle={styles.bodyContent}
          keyboardShouldPersistTaps="handled"
        >
          {!shouldFetchSearch(debouncedInput) ? (
            <Text style={styles.hint}>
              Type a product, brand, or store name to see suggestions.
            </Text>
          ) : suggestionsQuery.isError ? (
            <View style={styles.messageBlock}>
              <Text style={styles.errorTitle}>Suggestions unavailable</Text>
              <Text style={styles.errorBody}>
                {getSearchErrorMessage(suggestionsQuery.error)}
              </Text>
              <Pressable
                style={styles.retry}
                onPress={() => void suggestionsQuery.refetch()}
              >
                <Text style={styles.retryText}>Retry</Text>
              </Pressable>
            </View>
          ) : (
            <SuggestionList
              suggestions={suggestionsQuery.data?.suggestions ?? []}
              isLoading={suggestionsQuery.isFetching}
              onSelect={handleSelectSuggestion}
            />
          )}
        </ScrollView>
      ) : (
        <ScrollView
          style={styles.body}
          contentContainerStyle={styles.bodyContent}
          keyboardShouldPersistTaps="handled"
        >
          {productsQuery.isLoading && hits.length === 0 ? (
            <View style={styles.centered}>
              <ActivityIndicator size="large" color="#0a7ea4" />
              <Text style={styles.muted}>Searching…</Text>
            </View>
          ) : productsQuery.isError && hits.length === 0 ? (
            <View style={styles.messageBlock}>
              <Text style={styles.errorTitle}>Search unavailable</Text>
              <Text style={styles.errorBody}>
                {getSearchErrorMessage(productsQuery.error)}
              </Text>
              <Pressable
                style={styles.retry}
                onPress={() => void productsQuery.refetch()}
              >
                <Text style={styles.retryText}>Retry</Text>
              </Pressable>
            </View>
          ) : hits.length === 0 ? (
            <Text style={styles.hint}>No products found for “{submittedQuery}”.</Text>
          ) : (
            <>
              <Text style={styles.resultsMeta}>
                {total} result{total === 1 ? '' : 's'} for “{submittedQuery}”
              </Text>
              <View style={styles.grid}>
                {hits.map((hit) => (
                  <SearchResultCard key={hit.id} hit={hit} journey={journey} />
                ))}
              </View>
              {productsQuery.isFetchNextPageError && hits.length > 0 ? (
                <View style={styles.messageBlock}>
                  <Text style={styles.errorBody}>
                    Could not load more results. Your current results are still shown.
                  </Text>
                  <Pressable
                    style={styles.retry}
                    onPress={() => void productsQuery.fetchNextPage()}
                    disabled={productsQuery.isFetchingNextPage}
                  >
                    <Text style={styles.retryText}>
                      {productsQuery.isFetchingNextPage ? 'Retrying…' : 'Retry'}
                    </Text>
                  </Pressable>
                </View>
              ) : productsQuery.hasNextPage ? (
                <Pressable
                  style={styles.loadMore}
                  onPress={() => void productsQuery.fetchNextPage()}
                  disabled={productsQuery.isFetchingNextPage}
                >
                  <Text style={styles.loadMoreText}>
                    {productsQuery.isFetchingNextPage ? 'Loading…' : 'Load more'}
                  </Text>
                </Pressable>
              ) : null}
            </>
          )}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: '#fff',
    paddingTop: 16,
  },
  heading: {
    fontSize: 22,
    fontWeight: '700',
    paddingHorizontal: 16,
  },
  context: {
    fontSize: 13,
    color: '#666',
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  body: {
    flex: 1,
  },
  bodyContent: {
    paddingBottom: 40,
  },
  hint: {
    paddingHorizontal: 16,
    paddingVertical: 24,
    color: '#666',
    fontSize: 14,
  },
  resultsMeta: {
    paddingHorizontal: 16,
    marginBottom: 12,
    fontSize: 13,
    color: '#555',
  },
  grid: {
    paddingHorizontal: 16,
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  centered: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 8,
  },
  muted: {
    color: '#666',
    fontSize: 14,
  },
  messageBlock: {
    paddingHorizontal: 16,
    paddingVertical: 24,
    alignItems: 'center',
  },
  errorTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#222',
    marginBottom: 8,
  },
  errorBody: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
    marginBottom: 16,
  },
  retry: {
    backgroundColor: '#0a7ea4',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
  },
  retryText: {
    color: '#fff',
    fontWeight: '600',
  },
  loadMore: {
    marginHorizontal: 16,
    marginTop: 8,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#0a7ea4',
    alignItems: 'center',
  },
  loadMoreText: {
    color: '#0a7ea4',
    fontWeight: '600',
  },
});
