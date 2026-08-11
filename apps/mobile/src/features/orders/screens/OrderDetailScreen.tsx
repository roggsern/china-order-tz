import { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { CancelOrderButton } from '../components/CancelOrderButton';
import { ContinuePaymentButton } from '../components/ContinuePaymentButton';
import { OrderItemRow } from '../components/OrderItemRow';
import { OrderPaymentBlock } from '../components/OrderPaymentBlock';
import { OrderSummaryBlock } from '../components/OrderSummaryBlock';
import { OrderTimeline } from '../components/OrderTimeline';
import { useOrderDetail } from '../hooks/useOrders';
import { isOrderPayableFromServer } from '../utils/isOrderPayable';
import { hasOrderTrackingEntry } from '../utils/hasOrderTrackingEntry';
import { shouldOfferCancel } from '../utils/mapOrders';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import {
  buildOrderDetailHref,
  buildOrderTrackingHref,
} from '../utils/orderRoutes';

type Props = {
  orderId: string;
};

export function OrderDetailScreen({ orderId }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const detailQuery = useOrderDetail(orderId);
  const [cancelHidden, setCancelHidden] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  if (authStatus !== 'authenticated') {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Order</Text>
        <Text style={styles.body}>Please sign in to view this order.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() =>
            router.push(buildLoginHref(buildOrderDetailHref(orderId)))
          }
        >
          <Text style={styles.primaryButtonText}>Sign in</Text>
        </Pressable>
      </View>
    );
  }

  if (detailQuery.isLoading && !detailQuery.data) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#0a7ea4" />
        <Text style={styles.muted}>Loading order…</Text>
      </View>
    );
  }

  if (detailQuery.isError && !detailQuery.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Order unavailable</Text>
        <Text style={styles.body}>
          {getOrderErrorMessage(detailQuery.error)}
        </Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => void detailQuery.refetch()}
        >
          <Text style={styles.primaryButtonText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  const order = detailQuery.data;
  if (!order) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Order not found</Text>
      </View>
    );
  }

  const offerCancel =
    !cancelHidden &&
    shouldOfferCancel({
      status: order.status,
      canCancel: order.canCancel,
    });
  const offerContinuePayment = isOrderPayableFromServer(order);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={detailQuery.isRefetching}
          onRefresh={() => void detailQuery.refetch()}
        />
      }
    >
      <Text style={styles.heading}>
        {order.orderNumber ?? order.id}
      </Text>
      {order.journeyLabel ? (
        <Text style={styles.journey}>{order.journeyLabel}</Text>
      ) : null}
      <Text style={styles.status}>
        {order.statusLabel ?? order.status ?? 'Status unavailable'}
      </Text>
      {order.createdAt ? (
        <Text style={styles.meta}>{formatCustomerDateTime(order.createdAt)}</Text>
      ) : null}

      {actionMessage ? (
        <Text style={styles.notice}>{actionMessage}</Text>
      ) : null}

      <Text style={styles.section}>Items</Text>
      {order.items.map((item) => (
        <OrderItemRow
          key={item.id}
          item={item}
          currency={order.currency}
        />
      ))}

      <OrderSummaryBlock summary={order.summary} currency={order.currency} />

      {order.payment ? <OrderPaymentBlock payment={order.payment} /> : null}

      <ContinuePaymentButton
        orderId={orderId}
        enabled={offerContinuePayment}
      />

      <OrderTimeline progress={order.progress} />

      {order.shipment?.status ? (
        <View style={styles.shipmentBox}>
          <Text style={styles.section}>Shipment</Text>
          <Text style={styles.meta}>{order.shipment.status}</Text>
        </View>
      ) : null}

      {hasOrderTrackingEntry(order) ? (
        <Pressable
          style={styles.secondaryButton}
          onPress={() => router.push(buildOrderTrackingHref(orderId))}
        >
          <Text style={styles.secondaryButtonText}>View tracking</Text>
        </Pressable>
      ) : null}

      <CancelOrderButton
        orderId={orderId}
        enabled={offerCancel}
        onCancelled={() => {
          setActionMessage('Order cancellation recorded.');
          void detailQuery.refetch();
        }}
        onRejected={(message) => {
          setCancelHidden(true);
          setActionMessage(message);
          void detailQuery.refetch();
        }}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { padding: 16, paddingBottom: 40 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  heading: { fontSize: 22, fontWeight: '700', color: '#111' },
  title: { fontSize: 18, fontWeight: '700', color: '#222' },
  journey: {
    marginTop: 6,
    alignSelf: 'flex-start',
    fontSize: 12,
    fontWeight: '600',
    color: '#0a7ea4',
    backgroundColor: '#e8f6fa',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    overflow: 'hidden',
  },
  status: { marginTop: 8, fontSize: 15, fontWeight: '600', color: '#333' },
  meta: { marginTop: 4, fontSize: 13, color: '#666' },
  body: { fontSize: 14, color: '#666', textAlign: 'center' },
  muted: { marginTop: 8, color: '#666' },
  notice: {
    marginTop: 12,
    color: '#0a7ea4',
    fontSize: 14,
    fontWeight: '600',
  },
  section: {
    marginTop: 18,
    marginBottom: 4,
    fontSize: 15,
    fontWeight: '700',
    color: '#111',
  },
  shipmentBox: { marginTop: 8 },
  primaryButton: {
    marginTop: 12,
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 20,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '700' },
  secondaryButton: {
    marginTop: 16,
    borderWidth: 1,
    borderColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryButtonText: { color: '#0a7ea4', fontWeight: '700' },
});
