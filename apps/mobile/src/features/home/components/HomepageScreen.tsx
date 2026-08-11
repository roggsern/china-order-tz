import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useJourneyStore } from '@/src/core/auth';
import { journeyLabelFromChannel } from '@/src/features/cart/utils/journeyLabel';
import { useHomepage } from '../hooks/useHomepage';
import { getHomepageErrorMessage } from '../utils/homepageErrorMessage';
import { HomepageSections } from './HomepageSections';
import { JourneySwitcher } from './JourneySwitcher';

export function HomepageScreen() {
  const journey = useJourneyStore((s) => s.journey);
  const query = useHomepage();

  if (query.isLoading && !query.data) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#0a7ea4" />
        <Text style={styles.muted}>Loading homepage…</Text>
      </View>
    );
  }

  // Keep cached homepage when background refetch fails.
  if (query.isError && !query.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.errorTitle}>Homepage unavailable</Text>
        <Text style={styles.errorBody}>{getHomepageErrorMessage(query.error)}</Text>
        <Pressable style={styles.retry} onPress={() => void query.refetch()}>
          <Text style={styles.retryText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  const view = query.data;
  const empty =
    !view?.layout && (view?.sections.length ?? 0) === 0;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />
      }
    >
      <Text style={styles.context}>{journeyLabelFromChannel(journey)}</Text>
      <JourneySwitcher />

      {empty ? (
        <Text style={styles.empty}>
          {view?.meta.message
            ? 'Homepage content is not published for this shopping journey yet. You can still Browse, Search, and shop.'
            : 'No homepage layout for this commerce context.'}
        </Text>
      ) : (
        <HomepageSections sections={view?.sections ?? []} />
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    paddingTop: 16,
    paddingBottom: 40,
  },
  context: {
    fontSize: 12,
    color: '#666',
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  empty: {
    paddingHorizontal: 16,
    fontSize: 14,
    color: '#666',
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
  },
  muted: {
    marginTop: 12,
    color: '#666',
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
  },
  errorBody: {
    fontSize: 14,
    color: '#555',
    textAlign: 'center',
    marginBottom: 16,
  },
  retry: {
    backgroundColor: '#0a7ea4',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
  },
  retryText: {
    color: '#fff',
    fontWeight: '600',
  },
});
