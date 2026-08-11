import { StyleSheet, Text, View } from 'react-native';
import type { PaymentTransaction } from '../models/types';
import {
  formatPaymentMoney,
  paymentStatusLabel,
} from '../utils/mapPayment';

type Props = {
  transaction: PaymentTransaction;
};

export function PaymentStatusCard({ transaction }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>Payment status</Text>
      <Text style={styles.status}>{paymentStatusLabel(transaction.status)}</Text>
      <Text style={styles.meta}>
        Amount: {formatPaymentMoney(transaction.amount, transaction.currency)}
      </Text>
      {transaction.merchantReference ? (
        <Text style={styles.meta}>Ref: {transaction.merchantReference}</Text>
      ) : null}
      {transaction.order?.orderNumber ? (
        <Text style={styles.meta}>Order: {transaction.order.orderNumber}</Text>
      ) : null}
      <Text style={styles.note}>
        Final status is confirmed by the server after reconciliation and refresh.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 12,
    padding: 14,
    borderRadius: 10,
    backgroundColor: '#f5f7f8',
  },
  label: { fontSize: 13, color: '#666', marginBottom: 4 },
  status: { fontSize: 22, fontWeight: '700', color: '#0a7ea4', textTransform: 'capitalize' },
  meta: { marginTop: 6, fontSize: 13, color: '#444' },
  note: { marginTop: 10, fontSize: 12, color: '#666', lineHeight: 17 },
});
