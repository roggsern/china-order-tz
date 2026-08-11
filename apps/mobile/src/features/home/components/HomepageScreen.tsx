import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
} from 'react-native';
import { router } from 'expo-router';
import { useJourneyStore } from '@/src/core/auth';
import { journeyLabelFromChannel } from '@/src/features/cart/utils/journeyLabel';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, spacing, typography } from '@/src/shared/theme';
import { useHomepage } from '../hooks/useHomepage';
import { getHomepageErrorMessage } from '../utils/homepageErrorMessage';
import { HomepageSections } from './HomepageSections';
import { JourneySwitcher } from './JourneySwitcher';

export function HomepageScreen() {
  const journey = useJourneyStore((s) => s.journey);
  const query = useHomepage();

  if (query.isLoading && !query.data) {
    return <ScreenLoadingState showBrand label="Loading homepage…" />;
  }

  if (query.isError && !query.data) {
    return (
      <EmptyState
        title="Homepage unavailable"
        message={getHomepageErrorMessage(query.error)}
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
        style={styles.centered}
      />
    );
  }

  const view = query.data;
  const empty = !view?.layout && (view?.sections.length ?? 0) === 0;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={query.isRefetching}
          onRefresh={() => void query.refetch()}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
    >
      <Text style={styles.context}>{journeyLabelFromChannel(journey)}</Text>
      <JourneySwitcher />

      {empty ? (
        <EmptyState
          title="Nothing published yet"
          message={
            view?.meta.message
              ? 'Homepage content is not published for this shopping journey yet. You can still browse and shop.'
              : 'No homepage layout for this commerce context.'
          }
          actionLabel="Browse products"
          onActionPress={() => router.push('/(app)/(tabs)/browse')}
          style={styles.empty}
        />
      ) : (
        <HomepageSections sections={view?.sections ?? []} />
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  context: {
    ...typography.caption,
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.sm,
  },
  empty: {
    paddingVertical: spacing.xxl,
  },
  centered: {
    flex: 1,
    backgroundColor: colors.background,
  },
});
