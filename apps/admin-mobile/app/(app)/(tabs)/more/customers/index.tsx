import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { fetchCustomers } from '@/src/features/customers/api/customersApi';
import { useDebouncedValue } from '@/src/shared/hooks/useDebouncedValue';
import { EmptyState, ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function CustomersListScreen() {
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search);

  const query = useQuery({
    queryKey: ['admin', 'customers', debouncedSearch, page],
    queryFn: () => fetchCustomers(debouncedSearch, page),
  });

  return (
    <>
      <Stack.Screen options={{ title: 'Customers' }} />
      <View style={styles.container}>
        <TextInput
          value={search}
          onChangeText={(value) => {
            setSearch(value);
            setPage(1);
          }}
          placeholder="Search name, email, phone…"
          placeholderTextColor={colors.textMuted}
          style={styles.search}
        />

        {query.isLoading ? (
          <LoadingState label="Loading customers…" />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : (
          <FlatList
            data={query.data?.data ?? []}
            keyExtractor={(item) => item.id}
            ListEmptyComponent={<EmptyState title="No customers found" />}
            contentContainerStyle={styles.list}
            renderItem={({ item }) => (
              <Pressable
                style={styles.card}
                onPress={() => router.push(`/(app)/(tabs)/more/customers/${item.id}`)}
              >
                <Text style={styles.name}>{item.name ?? 'Unnamed'}</Text>
                <Text style={styles.meta}>{item.email ?? '—'}</Text>
                <Text style={styles.meta}>
                  {item.customer_code ?? item.id} · {item.lifecycle_status ?? '—'}
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
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  search: {
    margin: spacing.lg,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radii.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    color: colors.text,
  },
  list: { paddingHorizontal: spacing.lg, paddingBottom: spacing.lg },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: spacing.sm,
  },
  name: { fontSize: 15, fontWeight: '700', color: colors.navy },
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
