import { Pressable, StyleSheet, Text, View } from 'react-native';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { ContinuePaymentButton } from './ContinuePaymentButton';
import type { OrderListItem } from '../models/types';
import { isOrderPayableFromServer } from '../utils/isOrderPayable';
import { formatOrderMoney } from '../utils/mapOrders';

type Props = {
  order: OrderListItem;
  onPress: () => void;
};

export function OrderListCard({ order, onPress }: Props) {
  const previewName = order.preview?.primaryItem?.name;
  const extra = order.preview?.extraItems ?? 0;
  const offerContinuePayment = isOrderPayableFromServer(order);

  return (
    <View style={styles.card}>
      <Pressable onPress={onPress} accessibilityRole="button">
        <View style={styles.row}>
          <Text style={styles.orderNumber}>
            {order.orderNumber ?? order.id}
          </Text>
          {order.journeyLabel ? (
            <Text style={styles.journey}>{order.journeyLabel}</Text>
          ) : null}
        </View>

        <Text style={styles.status}>
          {order.statusLabel ?? order.status ?? 'Status unavailable'}
        </Text>

        {order.paymentStatus ? (
          <Text style={styles.meta}>Payment: {order.paymentStatus}</Text>
        ) : null}

        {order.createdAt ? (
          <Text style={styles.meta}>{formatCustomerDateTime(order.createdAt)}</Text>
        ) : null}

        {previewName ? (
          <Text style={styles.preview} numberOfLines={1}>
            {previewName}
            {extra > 0 ? ` +${extra} more` : ''}
          </Text>
        ) : null}

        <Text style={styles.total}>
          {formatOrderMoney(order.grandTotal, order.currency)}
        </Text>
      </Pressable>

      <ContinuePaymentButton
        orderId={order.id}
        enabled={offerContinuePayment}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    backgroundColor: '#fff',
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
  },
  orderNumber: { fontSize: 16, fontWeight: '700', color: '#111', flex: 1 },
  journey: {
    fontSize: 11,
    fontWeight: '600',
    color: '#0a7ea4',
    backgroundColor: '#e8f6fa',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    overflow: 'hidden',
  },
  status: { marginTop: 6, fontSize: 14, fontWeight: '600', color: '#333' },
  meta: { marginTop: 4, fontSize: 13, color: '#666' },
  preview: { marginTop: 6, fontSize: 13, color: '#444' },
  total: { marginTop: 8, fontSize: 15, fontWeight: '700', color: '#111' },
});
