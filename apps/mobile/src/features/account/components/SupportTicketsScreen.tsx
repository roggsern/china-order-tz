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
import { Badge } from '@/src/shared/ui/Badge';
import { colors, spacing, typography } from '@/src/shared/theme';
import { useSupportTickets } from '../hooks/useSupportTickets';
import type { SupportTicket } from '../api/supportApi';

function TicketRow({ ticket }: { ticket: SupportTicket }) {
  return (
    <Pressable
      style={styles.row}
      onPress={() =>
        router.push(`/(app)/account/support/${encodeURIComponent(ticket.id)}` as never)
      }
      accessibilityRole="button"
    >
      <View style={styles.rowTop}>
        <Text style={styles.subject} numberOfLines={2}>
          {ticket.subject}
        </Text>
        {ticket.statusLabel || ticket.status ? (
          <Badge
            label={ticket.statusLabel ?? ticket.status ?? ''}
            tone="neutral"
          />
        ) : null}
      </View>
      <Text style={styles.meta} numberOfLines={1}>
        {ticket.ticketNumber ?? ticket.id}
        {ticket.categoryLabel ? ` · ${ticket.categoryLabel}` : ''}
      </Text>
      {ticket.createdAt ? (
        <Text style={styles.meta}>{formatCustomerDateTime(ticket.createdAt)}</Text>
      ) : null}
    </Pressable>
  );
}

export function SupportTicketsScreen() {
  const query = useSupportTickets();

  if (query.isLoading && !query.data) {
    return <ScreenLoadingState label="Loading support…" />;
  }

  if (query.isError && !query.data) {
    return (
      <EmptyState
        title="Support unavailable"
        message="We could not load your tickets. Please try again."
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
      />
    );
  }

  const tickets = query.data ?? [];

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <View style={styles.header}>
        <Text style={styles.heading}>Support</Text>
        <PrimaryButton
          label="New ticket"
          onPress={() =>
            router.push('/(app)/account/support-new' as never)
          }
          style={styles.newButton}
        />
      </View>

      {tickets.length === 0 ? (
        <EmptyState
          title="No tickets yet"
          message="Create a support ticket when you need help with an order or account issue."
          actionLabel="New ticket"
          onActionPress={() =>
            router.push('/(app)/account/support-new' as never)
          }
        />
      ) : (
        <FlatList
          data={tickets}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.list}
          refreshing={query.isRefetching}
          onRefresh={() => void query.refetch()}
          renderItem={({ item }) => <TicketRow ticket={item} />}
        />
      )}
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
  newButton: { alignSelf: 'flex-start' },
  list: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.huge,
  },
  row: {
    paddingVertical: spacing.md,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  rowTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.sm,
    alignItems: 'flex-start',
  },
  subject: { ...typography.bodyStrong, flex: 1 },
  meta: { ...typography.caption, marginTop: spacing.xs },
});
