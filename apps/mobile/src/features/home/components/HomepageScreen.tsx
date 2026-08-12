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
        title="Unable to load home"
        message={getHomepageErrorMessage(query.error)}
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
        style={styles.centered}
      />
    );
  }

  const view = query.data;
  const sections = view?.sections ?? [];
  const empty = sections.length === 0;

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
          title="Start shopping"
          message="Shop products for this shopping journey, or switch journey above."
          actionLabel="Shop products"
          onActionPress={() => router.push('/(app)/(tabs)/browse')}
          style={styles.empty}
        />
      ) : (
        <HomepageSections sections={sections} />
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
    paddingTop: spacing.md,
    paddingBottom: spacing.huge,
  },
  context: {
    ...typography.caption,
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.xs,
  },
  empty: {
    paddingVertical: spacing.xxl,
  },
  centered: {
    flex: 1,
    backgroundColor: colors.background,
  },
});
