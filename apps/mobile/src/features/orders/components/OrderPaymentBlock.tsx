import { StyleSheet, Text, View } from 'react-native';
import type { OrderPaymentSnapshot } from '../models/types';
import { formatOrderMoney } from '../utils/mapOrders';

type Props = {
  payment: OrderPaymentSnapshot;
};

export function OrderPaymentBlock({ payment }: Props) {
  const currency = payment.currency ?? 'TZS';

  return (
    <View style={styles.block}>
      <Text style={styles.title}>Payment</Text>
      {payment.paymentStatus ? (
        <Text style={styles.line}>Status: {payment.paymentStatus}</Text>
      ) : null}
      {payment.paymentMethod || payment.provider ? (
        <Text style={styles.line}>
          Method: {payment.paymentMethod ?? payment.provider}
        </Text>
      ) : null}
      {payment.reference ? (
        <Text style={styles.line}>Reference: {payment.reference}</Text>
      ) : null}
      {payment.amount != null ? (
        <Text style={styles.line}>
          Amount: {formatOrderMoney(payment.amount, currency)}
        </Text>
      ) : null}
      {payment.paidAt ? (
        <Text style={styles.line}>Paid at: {payment.paidAt}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  block: {
    marginTop: 16,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  title: { fontSize: 15, fontWeight: '700', color: '#111', marginBottom: 8 },
  line: { fontSize: 14, color: '#444', marginBottom: 4 },
});
