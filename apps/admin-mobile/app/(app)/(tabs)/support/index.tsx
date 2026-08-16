import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { fetchSupportTickets } from '@/src/features/support/api/supportApi';
import { EmptyState, ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function SupportListScreen() {
  const [page, setPage] = useState(1);

  const query = useQuery({
    queryKey: ['admin', 'support', page],
    queryFn: () => fetchSupportTickets(page),
  });

  return (
    <>
      <Stack.Screen options={{ title: 'Support' }} />
      {query.isLoading ? (
        <LoadingState label="Loading tickets…" />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={() => query.refetch()} />
      ) : (
        <FlatList
          data={query.data?.data ?? []}
          keyExtractor={(item) => item.id}
          refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => query.refetch()} />}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<EmptyState title="No support tickets" />}
          renderItem={({ item }) => (
            <Pressable
              style={styles.card}
              onPress={() => router.push(`/(app)/(tabs)/support/${item.id}`)}
            >
              <Text style={styles.number}>{item.ticket_number}</Text>
              <Text style={styles.subject}>{item.subject}</Text>
              <Text style={styles.meta}>
                {item.status_label ?? item.status} · {item.priority_label ?? item.priority ?? '—'}
              </Text>
            </Pressable>
          )}
          ListFooterComponent={
            query.data && query.data.meta.last_page > 1 ? (
              <View style={styles.pagination}>
                <Pressable
                  disabled={page <= 1}
                  onPress={() => setPage((p) => Math.max(1, p - 1))}
                  style={[styles.pageBtn, page <= 1 && styles.pageBtnDisabled]}
                >
                  <Text style={styles.pageBtnText}>Previous</Text>
                </Pressable>
                <Text style={styles.pageLabel}>
                  Page {query.data.meta.current_page} / {query.data.meta.last_page}
                </Text>
                <Pressable
                  disabled={page >= query.data.meta.last_page}
                  onPress={() => setPage((p) => p + 1)}
                  style={[styles.pageBtn, page >= query.data.meta.last_page && styles.pageBtnDisabled]}
                >
                  <Text style={styles.pageBtnText}>Next</Text>
                </Pressable>
              </View>
            ) : null
          }
        />
      )}
    </>
  );
}

const styles = StyleSheet.create({
  list: { padding: spacing.lg, backgroundColor: colors.background },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: spacing.sm,
  },
  number: { fontSize: 12, color: colors.textMuted, fontWeight: '600' },
  subject: { marginTop: spacing.xs, fontSize: 15, fontWeight: '700', color: colors.navy },
  meta: { marginTop: spacing.xs, fontSize: 12, color: colors.textMuted },
  pagination: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.md,
  },
  pageBtn: {
    backgroundColor: colors.navy,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radii.sm,
  },
  pageBtnDisabled: { opacity: 0.4 },
  pageBtnText: { color: '#fff', fontWeight: '600' },
  pageLabel: { color: colors.textMuted, fontSize: 13 },
});
