import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { fetchLowStock } from '@/src/features/inventory/api/lowStockApi';
import { EmptyState, ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function LowStockScreen() {
  const query = useQuery({
    queryKey: ['admin', 'inventory', 'low-stock'],
    queryFn: fetchLowStock,
  });

  return (
    <>
      <Stack.Screen options={{ title: 'Low stock' }} />
      {query.isLoading ? (
        <LoadingState label="Loading inventory…" />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={() => query.refetch()} />
      ) : (
        <FlatList
          data={query.data ?? []}
          keyExtractor={(item, index) => item.variant_inventory_id ?? String(index)}
          refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => query.refetch()} />}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<EmptyState title="No low stock items" message="All SKUs are above reorder levels." />}
          renderItem={({ item }) => (
            <View style={styles.card}>
              <Text style={styles.name}>{item.product_name ?? item.sku ?? 'SKU'}</Text>
              <Text style={styles.meta}>{item.store_name ?? '—'}</Text>
              <Text style={styles.meta}>
                Available {item.available ?? 0} / reorder {item.reorder_level ?? 0}
              </Text>
              <Text style={[styles.badge, item.status === 'out_of_stock' ? styles.out : styles.low]}>
                {(item.status ?? 'low_stock').replace(/_/g, ' ')}
              </Text>
            </View>
          )}
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
    gap: spacing.xs,
  },
  name: { fontSize: 15, fontWeight: '700', color: colors.navy },
  meta: { fontSize: 12, color: colors.textMuted },
  badge: {
    alignSelf: 'flex-start',
    marginTop: spacing.xs,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: radii.sm,
    overflow: 'hidden',
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'capitalize',
  },
  low: { backgroundColor: '#fef3c7', color: colors.warning },
  out: { backgroundColor: '#fee2e2', color: colors.danger },
});
