import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, StyleSheet, Text, TextInput, View } from 'react-native';

import { buildReplyPayload, fetchSupportTicket, replyToTicket } from '@/src/features/support/api/supportApi';
import { ErrorState, LoadingState, PrimaryButton } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function SupportDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [message, setMessage] = useState('');
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['admin', 'support', id],
    queryFn: () => fetchSupportTicket(id),
    enabled: Boolean(id),
  });

  const replyMutation = useMutation({
    mutationFn: (body: string) => replyToTicket(id, buildReplyPayload(body, true)),
    onSuccess: () => {
      setMessage('');
      queryClient.invalidateQueries({ queryKey: ['admin', 'support', id] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'support'] });
    },
  });

  if (query.isLoading) return <LoadingState label="Loading ticket…" />;
  if (query.isError || !query.data) {
    return <ErrorState error={query.error} onRetry={() => query.refetch()} />;
  }

  const ticket = query.data;

  return (
    <>
      <Stack.Screen options={{ title: ticket.ticket_number }} />
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.subject}>{ticket.subject}</Text>
          <Text style={styles.meta}>
            {ticket.status_label ?? ticket.status} · {ticket.category_label ?? ticket.category ?? '—'}
          </Text>
          {ticket.assigned_admin?.name ? (
            <Text style={styles.meta}>Assigned: {ticket.assigned_admin.name}</Text>
          ) : null}
        </View>

        <FlatList
          data={ticket.messages ?? []}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.messages}
          renderItem={({ item }) => (
            <View
              style={[
                styles.messageBubble,
                item.sender_type === 'admin' ? styles.adminBubble : styles.customerBubble,
              ]}
            >
              <Text style={styles.sender}>{item.sender_type ?? 'unknown'}</Text>
              <Text style={[styles.messageText, item.sender_type !== 'admin' && styles.messageTextCustomer]}>
                {item.message}
              </Text>
            </View>
          )}
        />

        <View style={styles.composer}>
          <TextInput
            value={message}
            onChangeText={setMessage}
            placeholder="Write a reply…"
            placeholderTextColor={colors.textMuted}
            style={styles.input}
            multiline
          />
          <PrimaryButton
            label="Send reply"
            onPress={() => replyMutation.mutate(message)}
            disabled={!message.trim()}
            loading={replyMutation.isPending}
          />
        </View>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: {
    padding: spacing.lg,
    backgroundColor: colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  subject: { fontSize: 16, fontWeight: '700', color: colors.navy },
  meta: { marginTop: spacing.xs, fontSize: 12, color: colors.textMuted },
  messages: { padding: spacing.lg, gap: spacing.sm },
  messageBubble: {
    borderRadius: radii.md,
    padding: spacing.md,
    marginBottom: spacing.sm,
    maxWidth: '92%',
  },
  adminBubble: { alignSelf: 'flex-end', backgroundColor: colors.navyMuted },
  customerBubble: { alignSelf: 'flex-start', backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border },
  sender: { fontSize: 10, color: colors.goldMuted, textTransform: 'uppercase', marginBottom: spacing.xs },
  messageText: { color: '#fff', fontSize: 14 },
  messageTextCustomer: { color: colors.text },
  composer: {
    padding: spacing.lg,
    backgroundColor: colors.surface,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    gap: spacing.sm,
  },
  input: {
    minHeight: 80,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radii.md,
    padding: spacing.md,
    color: colors.text,
    textAlignVertical: 'top',
  },
});
