import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useJourneyStore } from '@/src/core/auth';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';
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
      <View style={styles.header}>
        <Text style={styles.eyebrow}>Search</Text>
        <Text style={styles.heading}>Find products</Text>
        <Text style={styles.context}>{journeySearchLabel(journey)}</Text>
      </View>

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
            <EmptyState
              title="Start typing"
              message="Search products, brands, or stores in your current shopping journey."
              style={styles.emptyPad}
            />
          ) : suggestionsQuery.isError ? (
            <EmptyState
              title="Suggestions unavailable"
              message={getSearchErrorMessage(suggestionsQuery.error)}
              actionLabel="Retry"
              onActionPress={() => void suggestionsQuery.refetch()}
              style={styles.emptyPad}
            />
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
              <ActivityIndicator size="large" color={colors.primary} />
              <Text style={styles.muted}>Searching…</Text>
            </View>
          ) : productsQuery.isError && hits.length === 0 ? (
            <EmptyState
              title="Search unavailable"
              message={getSearchErrorMessage(productsQuery.error)}
              actionLabel="Retry"
              onActionPress={() => void productsQuery.refetch()}
              style={styles.emptyPad}
            />
          ) : hits.length === 0 ? (
            <EmptyState
              title="No results"
              message={`No products found for “${submittedQuery}”. Try another keyword.`}
              actionLabel="Clear search"
              onActionPress={() => {
                setInput('');
                setDebouncedInput('');
                setSubmittedQuery('');
                setMode('suggest');
              }}
              style={styles.emptyPad}
            />
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
                  <PrimaryButton
                    label={
                      productsQuery.isFetchingNextPage ? 'Retrying…' : 'Retry'
                    }
                    onPress={() => void productsQuery.fetchNextPage()}
                    disabled={productsQuery.isFetchingNextPage}
                    style={styles.loadMoreBtn}
                  />
                </View>
              ) : productsQuery.hasNextPage ? (
                <SecondaryButton
                  label={
                    productsQuery.isFetchingNextPage ? 'Loading…' : 'Load more'
                  }
                  onPress={() => void productsQuery.fetchNextPage()}
                  disabled={productsQuery.isFetchingNextPage}
                  style={styles.loadMoreBtn}
                />
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
    backgroundColor: colors.background,
    paddingTop: spacing.lg,
  },
  header: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.md,
  },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: {
    ...typography.heading,
  },
  context: {
    ...typography.caption,
    marginTop: spacing.xs,
  },
  body: {
    flex: 1,
  },
  bodyContent: {
    paddingBottom: spacing.huge,
  },
  emptyPad: {
    paddingVertical: spacing.xxl,
  },
  resultsMeta: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.md,
    ...typography.caption,
  },
  grid: {
    paddingHorizontal: spacing.lg,
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  centered: {
    paddingVertical: spacing.huge,
    alignItems: 'center',
    gap: spacing.sm,
  },
  muted: {
    ...typography.body,
  },
  messageBlock: {
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xxl,
    alignItems: 'center',
  },
  errorBody: {
    ...typography.body,
    textAlign: 'center',
    marginBottom: spacing.lg,
  },
  loadMoreBtn: {
    marginHorizontal: spacing.lg,
    marginTop: spacing.sm,
    alignSelf: 'stretch',
  },
});
