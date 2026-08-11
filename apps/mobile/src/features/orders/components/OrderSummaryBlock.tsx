import { StyleSheet, Text, View } from 'react-native';
import type { OrderSummary } from '../models/types';
import { formatOrderMoney } from '../utils/mapOrders';

type Props = {
  summary: OrderSummary;
  currency: string;
};

export function OrderSummaryBlock({ summary, currency }: Props) {
  return (
    <View style={styles.block}>
      <Text style={styles.title}>Totals</Text>
      {summary.subtotal != null ? (
        <Text style={styles.line}>
          Subtotal: {formatOrderMoney(summary.subtotal, currency)}
        </Text>
      ) : null}
      {summary.shipping != null ? (
        <Text style={styles.line}>
          Shipping: {formatOrderMoney(summary.shipping, currency)}
        </Text>
      ) : null}
      {summary.discount != null ? (
        <Text style={styles.line}>
          Discount: {formatOrderMoney(summary.discount, currency)}
        </Text>
      ) : null}
      {summary.tax != null ? (
        <Text style={styles.line}>
          Tax: {formatOrderMoney(summary.tax, currency)}
        </Text>
      ) : null}
      <Text style={styles.grand}>
        Total: {formatOrderMoney(summary.grandTotal, currency)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  block: {
    marginTop: 16,
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#f8fafc',
  },
  title: { fontSize: 15, fontWeight: '700', color: '#111', marginBottom: 8 },
  line: { fontSize: 14, color: '#444', marginBottom: 4 },
  grand: { marginTop: 6, fontSize: 16, fontWeight: '700', color: '#111' },
});
