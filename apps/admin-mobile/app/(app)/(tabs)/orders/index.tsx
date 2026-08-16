import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import {
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import type { CommerceChannelCode } from '@/src/features/orders/api/ordersApi';
import { fetchOrders } from '@/src/features/orders/api/ordersApi';
import { useDebouncedValue } from '@/src/shared/hooks/useDebouncedValue';
import { ChannelBadge, EmptyState, ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

const STATUS_OPTIONS = ['all', 'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];
const CHANNEL_OPTIONS: { label: string; value?: CommerceChannelCode }[] = [
  { label: 'All channels' },
  { label: 'China Import', value: 'CHINA_IMPORT' },
  { label: 'TZ Local', value: 'TZ_LOCAL' },
];

export default function OrdersListScreen() {
  const [status, setStatus] = useState('all');
  const [channel, setChannel] = useState<CommerceChannelCode | undefined>();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search);

  const query = useQuery({
    queryKey: ['admin', 'orders', status, channel, debouncedSearch, page],
    queryFn: () =>
      fetchOrders({
        status,
        commerce_channel: channel,
        q: debouncedSearch,
        page,
      }),
  });

  return (
    <>
      <Stack.Screen options={{ title: 'Orders' }} />
      <View style={styles.container}>
        <TextInput
          value={search}
          onChangeText={(value) => {
            setSearch(value);
            setPage(1);
          }}
          placeholder="Search order #, customer…"
          placeholderTextColor={colors.textMuted}
          style={styles.search}
        />

        <View style={styles.filters}>
          {CHANNEL_OPTIONS.map((option) => {
            const active = channel === option.value;
            return (
              <Pressable
                key={option.label}
                style={[styles.chip, active && styles.chipActive]}
                onPress={() => {
                  setChannel(option.value);
                  setPage(1);
                }}
              >
                <Text style={[styles.chipText, active && styles.chipTextActive]}>{option.label}</Text>
              </Pressable>
            );
          })}
        </View>

        <FlatList
          horizontal
          data={STATUS_OPTIONS}
          keyExtractor={(item) => item}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.statusRow}
          renderItem={({ item }) => {
            const active = status === item;
            return (
              <Pressable
                style={[styles.chip, active && styles.chipActive]}
                onPress={() => {
                  setStatus(item);
                  setPage(1);
                }}
              >
                <Text style={[styles.chipText, active && styles.chipTextActive]}>
                  {item.replace(/_/g, ' ')}
                </Text>
              </Pressable>
            );
          }}
        />

        {query.isLoading ? (
          <LoadingState label="Loading orders…" />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : (
          <FlatList
            data={query.data?.data ?? []}
            keyExtractor={(item) => item.id}
            refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => query.refetch()} />}
            ListEmptyComponent={<EmptyState title="No orders found" message="Try adjusting filters or search." />}
            contentContainerStyle={styles.list}
            renderItem={({ item }) => (
              <Pressable
                style={styles.card}
                onPress={() => router.push(`/(app)/(tabs)/orders/${item.id}`)}
              >
                <View style={styles.cardHeader}>
                  <Text style={styles.orderNumber}>{item.order_number}</Text>
                  <ChannelBadge channel={item.commerce_channel_code} />
                </View>
                <Text style={styles.customer}>{item.user?.name ?? item.user?.email ?? 'Guest'}</Text>
                <Text style={styles.meta}>
                  {item.status_label ?? item.status} · {item.currency ?? 'TZS'}{' '}
                  {(item.grand_total ?? item.total ?? 0).toLocaleString()}
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
    marginBottom: spacing.sm,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radii.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    color: colors.text,
  },
  filters: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, paddingHorizontal: spacing.lg },
  statusRow: { gap: spacing.sm, paddingHorizontal: spacing.lg, paddingVertical: spacing.sm },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radii.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    backgroundColor: colors.surface,
  },
  chipActive: { backgroundColor: colors.navy, borderColor: colors.navy },
  chipText: { fontSize: 12, color: colors.text, textTransform: 'capitalize' },
  chipTextActive: { color: '#fff', fontWeight: '600' },
  list: { padding: spacing.lg, paddingTop: spacing.sm, gap: spacing.sm },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: spacing.sm,
  },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: spacing.sm },
  orderNumber: { fontSize: 15, fontWeight: '700', color: colors.navy, flex: 1 },
  customer: { marginTop: spacing.xs, color: colors.text, fontSize: 14 },
  meta: { marginTop: spacing.xs, color: colors.textMuted, fontSize: 12 },
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
