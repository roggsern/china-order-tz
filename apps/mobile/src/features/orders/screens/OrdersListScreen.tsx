import { useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, spacing, typography } from '@/src/shared/theme';
import { OrderListCard } from '../components/OrderListCard';
import { useOrdersList } from '../hooks/useOrders';
import type { OrderListItem } from '../models/types';
import { isOrdersListEmpty } from '../utils/mapOrders';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import {
  buildOrderDetailHref,
  buildOrdersListHref,
} from '../utils/orderRoutes';

export function OrdersListScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const listQuery = useOrdersList({ filter: 'all', perPage: 10 });

  const orders = useMemo(() => {
    const pages = listQuery.data?.pages ?? [];
    const seen = new Set<string>();
    const items: OrderListItem[] = [];
    for (const page of pages) {
      for (const order of page.orders) {
        if (seen.has(order.id)) continue;
        seen.add(order.id);
        items.push(order);
      }
    }
    return items;
  }, [listQuery.data?.pages]);

  const firstPage = listQuery.data?.pages?.[0];
  const empty = isOrdersListEmpty(firstPage) && orders.length === 0;

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Orders"
        message="Please sign in to view your orders."
        actionLabel="Sign in"
        onActionPress={() => router.push(buildLoginHref(buildOrdersListHref()))}
        style={styles.fill}
      />
    );
  }

  if (listQuery.isLoading && !listQuery.data) {
    return <ScreenLoadingState label="Loading orders…" />;
  }

  if (listQuery.isError && !listQuery.data) {
    return (
      <EmptyState
        title="Orders unavailable"
        message={getOrderErrorMessage(listQuery.error)}
        actionLabel="Retry"
        onActionPress={() => void listQuery.refetch()}
        style={styles.fill}
      />
    );
  }

  return (
    <FlatList
      style={styles.screen}
      contentContainerStyle={styles.content}
      data={orders}
      keyExtractor={(item) => item.id}
      refreshControl={
        <RefreshControl
          refreshing={listQuery.isRefetching && !listQuery.isFetchingNextPage}
          onRefresh={() => void listQuery.refetch()}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
      ListHeaderComponent={
        <View style={styles.header}>
          <Text style={styles.eyebrow}>Orders</Text>
          <Text style={styles.heading}>My orders</Text>
          <Text style={styles.subheading}>
            Status, payment, and totals come from the server.
          </Text>
        </View>
      }
      ListEmptyComponent={
        empty ? (
          <EmptyState
            title="No orders yet"
            message="When you complete a purchase, your orders will appear here."
            actionLabel="Shop products"
            onActionPress={() => router.push('/(app)/(tabs)/browse')}
            style={styles.empty}
          />
        ) : null
      }
      renderItem={({ item }) => (
        <OrderListCard
          order={item}
          onPress={() => router.push(buildOrderDetailHref(item.id))}
        />
      )}
      onEndReached={() => {
        if (listQuery.hasNextPage && !listQuery.isFetchingNextPage) {
          void listQuery.fetchNextPage();
        }
      }}
      onEndReachedThreshold={0.4}
      ListFooterComponent={
        listQuery.isFetchingNextPage ? (
          <ActivityIndicator style={styles.footer} color={colors.primary} />
        ) : listQuery.isFetchNextPageError && orders.length > 0 ? (
          <View style={styles.footerError}>
            <Text style={styles.footerErrorText}>
              Could not load more orders. Your current list is still shown.
            </Text>
            <PrimaryButton
              label="Retry"
              onPress={() => void listQuery.fetchNextPage()}
              style={styles.retry}
            />
          </View>
        ) : null
      }
    />
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
    flexGrow: 1,
  },
  fill: { flex: 1, backgroundColor: colors.background },
  header: { marginBottom: spacing.md },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: { ...typography.heading },
  subheading: {
    marginTop: spacing.xs,
    ...typography.caption,
  },
  empty: { paddingVertical: spacing.xxxl },
  footer: { marginVertical: spacing.lg },
  footerError: {
    marginVertical: spacing.lg,
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
  },
  footerErrorText: {
    ...typography.caption,
    textAlign: 'center',
  },
  retry: { minWidth: 140 },
});
