import { useState } from 'react';
import {
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { Badge } from '@/src/shared/ui/Badge';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  useSupportMutations,
  useSupportTicket,
} from '../hooks/useSupportTickets';

type Props = {
  ticketId: string;
};

export function SupportTicketDetailScreen({ ticketId }: Props) {
  const query = useSupportTicket(ticketId);
  const { reply } = useSupportMutations();
  const [message, setMessage] = useState('');

  if (query.isLoading && !query.data) {
    return <ScreenLoadingState label="Loading ticket…" />;
  }

  if (query.isError && !query.data) {
    return (
      <EmptyState
        title="Ticket unavailable"
        message="We could not load this support ticket."
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
      />
    );
  }

  const ticket = query.data;
  if (!ticket) {
    return (
      <EmptyState
        title="Ticket not found"
        message="This support ticket could not be loaded."
      />
    );
  }

  async function onReply() {
    const trimmed = message.trim();
    if (!trimmed) {
      Alert.alert('Empty reply', 'Write a message before sending.');
      return;
    }
    try {
      await reply.mutateAsync({ ticketId, message: trimmed });
      setMessage('');
    } catch (error) {
      Alert.alert(
        'Could not send',
        error instanceof Error ? error.message : 'Please try again.',
      );
    }
  }

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
      <Text style={styles.heading} numberOfLines={3}>
        {ticket.subject}
      </Text>
      <View style={styles.badges}>
        {ticket.statusLabel || ticket.status ? (
          <Badge
            label={ticket.statusLabel ?? ticket.status ?? ''}
            tone="neutral"
          />
        ) : null}
        {ticket.categoryLabel ? (
          <Badge label={ticket.categoryLabel} tone="brand" />
        ) : null}
      </View>
      <Text style={styles.meta}>
        {ticket.ticketNumber ?? ticket.id}
        {ticket.createdAt
          ? ` · ${formatCustomerDateTime(ticket.createdAt)}`
          : ''}
      </Text>

      <Text style={styles.section}>Conversation</Text>
      {ticket.messages.length === 0 ? (
        <Text style={styles.emptyThread}>No messages yet.</Text>
      ) : (
        ticket.messages.map((row) => (
          <View key={row.id} style={styles.messageBox}>
            <Text style={styles.sender}>
              {row.senderType === 'customer' ? 'You' : 'Support'}
            </Text>
            <Text style={styles.message}>{row.message}</Text>
            {row.createdAt ? (
              <Text style={styles.meta}>
                {formatCustomerDateTime(row.createdAt)}
              </Text>
            ) : null}
          </View>
        ))
      )}

      <Text style={styles.section}>Reply</Text>
      <TextInput
        value={message}
        onChangeText={setMessage}
        style={styles.input}
        multiline
        textAlignVertical="top"
        maxLength={5000}
        placeholder="Write a reply…"
        placeholderTextColor={colors.textMuted}
      />
      <PrimaryButton
        label="Send reply"
        loading={reply.isPending}
        disabled={reply.isPending}
        onPress={() => void onReply()}
        style={styles.button}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  heading: { ...typography.heading },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  meta: { ...typography.caption, marginTop: spacing.xs },
  section: {
    marginTop: spacing.xl,
    marginBottom: spacing.sm,
    ...typography.label,
    fontWeight: '700',
  },
  emptyThread: { ...typography.caption },
  messageBox: {
    marginBottom: spacing.md,
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: colors.backgroundMuted,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
  },
  sender: { ...typography.caption, fontWeight: '700', marginBottom: spacing.xs },
  message: { ...typography.body },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    minHeight: 110,
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.backgroundMuted,
  },
  button: { alignSelf: 'stretch', marginTop: spacing.md },
});
