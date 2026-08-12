import {
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, spacing, typography } from '@/src/shared/theme';
import { resolveNotificationDestinationFromSemantic } from '@/src/features/notifications';
import {
  useNotificationMutations,
  useNotifications,
} from '../hooks/useNotifications';
import type { CustomerNotification } from '../api/notificationsApi';

function NotificationRow({
  item,
  onPress,
}: {
  item: CustomerNotification;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.row, !item.isRead && styles.unread]}
      accessibilityRole="button"
    >
      <Text style={styles.title} numberOfLines={2}>
        {item.title ?? item.type ?? 'Notification'}
      </Text>
      {item.message ? (
        <Text style={styles.body} numberOfLines={3}>
          {item.message}
        </Text>
      ) : null}
      {item.createdAt ? (
        <Text style={styles.meta}>{formatCustomerDateTime(item.createdAt)}</Text>
      ) : null}
      {!item.isRead ? <Text style={styles.unreadLabel}>Unread</Text> : null}
    </Pressable>
  );
}

export function NotificationsScreen() {
  const query = useNotifications();
  const { markRead, markAll } = useNotificationMutations();

  if (query.isLoading && !query.data) {
    return <ScreenLoadingState label="Loading notifications…" />;
  }

  if (query.isError && !query.data) {
    return (
      <EmptyState
        title="Notifications unavailable"
        message="We could not load your inbox. Please try again."
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
      />
    );
  }

  const notifications = query.data ?? [];

  if (notifications.length === 0) {
    return (
      <EmptyState
        title="No notifications"
        message="You have no notifications yet."
      />
    );
  }

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <View style={styles.header}>
        <Text style={styles.heading}>Notifications</Text>
        <PrimaryButton
          label="Mark all read"
          loading={markAll.isPending}
          disabled={markAll.isPending}
          onPress={() => void markAll.mutateAsync()}
          style={styles.markAll}
        />
      </View>
      <FlatList
        data={notifications}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        refreshing={query.isRefetching}
        onRefresh={() => void query.refetch()}
        renderItem={({ item }) => (
          <NotificationRow
            item={item}
            onPress={() => {
              if (!item.isRead) {
                void markRead.mutateAsync(item.id);
              }
              const href = resolveNotificationDestinationFromSemantic(item.data);
              router.push(href as never);
            }}
          />
        )}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: { backgroundColor: colors.background, flex: 1 },
  header: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.sm,
    gap: spacing.sm,
  },
  heading: { ...typography.heading },
  markAll: { alignSelf: 'flex-start' },
  list: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.huge,
  },
  row: {
    paddingVertical: spacing.md,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  unread: {
    backgroundColor: colors.backgroundMuted,
    marginHorizontal: -spacing.sm,
    paddingHorizontal: spacing.sm,
    borderRadius: 8,
  },
  title: { ...typography.bodyStrong },
  body: { ...typography.caption, marginTop: spacing.xs },
  meta: { ...typography.caption, marginTop: spacing.xs, color: colors.textMuted },
  unreadLabel: {
    ...typography.caption,
    marginTop: spacing.xs,
    color: colors.primaryPressed,
    fontWeight: '700',
  },
});
