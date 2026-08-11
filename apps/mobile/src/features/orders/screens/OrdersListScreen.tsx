import { useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
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
      <View style={styles.centered}>
        <Text style={styles.title}>Orders</Text>
        <Text style={styles.body}>Please sign in to view your orders.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => router.push(buildLoginHref(buildOrdersListHref()))}
        >
          <Text style={styles.primaryButtonText}>Sign in</Text>
        </Pressable>
      </View>
    );
  }

  if (listQuery.isLoading && !listQuery.data) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#0a7ea4" />
        <Text style={styles.muted}>Loading orders…</Text>
      </View>
    );
  }

  if (listQuery.isError && !listQuery.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Orders unavailable</Text>
        <Text style={styles.body}>{getOrderErrorMessage(listQuery.error)}</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => void listQuery.refetch()}
        >
          <Text style={styles.primaryButtonText}>Retry</Text>
        </Pressable>
      </View>
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
        />
      }
      ListHeaderComponent={
        <View style={styles.header}>
          <Text style={styles.subheading}>
            Status, payment, and totals come from the server.
          </Text>
        </View>
      }
      ListEmptyComponent={
        empty ? (
          <View style={styles.empty}>
            <Text style={styles.emptyTitle}>No orders yet</Text>
            <Text style={styles.body}>
              When you complete a purchase, your orders will appear here.
            </Text>
          </View>
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
          <ActivityIndicator style={styles.footer} color="#0a7ea4" />
        ) : listQuery.isFetchNextPageError && orders.length > 0 ? (
          <View style={styles.footerError}>
            <Text style={styles.footerErrorText}>
              Could not load more orders. Your current list is still shown.
            </Text>
            <Pressable
              style={styles.primaryButton}
              onPress={() => void listQuery.fetchNextPage()}
            >
              <Text style={styles.primaryButtonText}>Retry</Text>
            </Pressable>
          </View>
        ) : null
      }
    />
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { padding: 16, paddingBottom: 40, flexGrow: 1 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  header: { marginBottom: 12 },
  heading: { fontSize: 22, fontWeight: '700', color: '#111' },
  title: { fontSize: 18, fontWeight: '700', color: '#222' },
  subheading: {
    marginTop: 6,
    fontSize: 13,
    color: '#666',
    lineHeight: 18,
  },
  body: { fontSize: 14, color: '#666', textAlign: 'center' },
  muted: { marginTop: 8, color: '#666' },
  empty: {
    marginTop: 40,
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 12,
  },
  emptyTitle: { fontSize: 17, fontWeight: '700', color: '#222' },
  primaryButton: {
    marginTop: 12,
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 20,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '700' },
  footer: { marginVertical: 16 },
  footerError: {
    marginVertical: 16,
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 12,
  },
  footerErrorText: {
    fontSize: 13,
    color: '#666',
    textAlign: 'center',
  },
});
