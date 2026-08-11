import { StyleSheet, Text, View } from 'react-native';
import type { CheckoutPrepare, CheckoutSession } from '../models/types';
import { formatCheckoutMoney } from '../utils/mapCheckout';

type Props = {
  prepare?: CheckoutPrepare | null;
  session?: CheckoutSession | null;
  currency?: string;
};

/** Server totals only — never recomputed. Prefer session totals when present. */
export function CheckoutTotals({ prepare, session, currency = 'TZS' }: Props) {
  const subtotal = session?.subtotal ?? prepare?.subtotal;
  const shipping =
    session?.shippingTotal ?? prepare?.shippingSummary.chinaShippingTotal;
  const discount = session?.discountTotal;
  const tax = session?.taxTotal;
  const grand = session?.grandTotal ?? prepare?.grandTotal;

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Totals</Text>
      <Row label="Subtotal" value={formatCheckoutMoney(subtotal, currency)} />
      {shipping != null ? (
        <Row label="Shipping" value={formatCheckoutMoney(shipping, currency)} />
      ) : null}
      {discount != null ? (
        <Row label="Discount" value={formatCheckoutMoney(discount, currency)} />
      ) : null}
      {tax != null ? (
        <Row label="Tax" value={formatCheckoutMoney(tax, currency)} />
      ) : null}
      <Row
        label="Grand total"
        value={formatCheckoutMoney(grand, currency)}
        strong
      />
    </View>
  );
}

function Row({
  label,
  value,
  strong,
}: {
  label: string;
  value: string;
  strong?: boolean;
}) {
  return (
    <View style={styles.row}>
      <Text style={[styles.label, strong ? styles.strong : null]}>{label}</Text>
      <Text style={[styles.value, strong ? styles.strongValue : null]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 16,
    padding: 14,
    borderRadius: 10,
    backgroundColor: '#f5f7f8',
  },
  title: { fontSize: 15, fontWeight: '700', marginBottom: 10, color: '#222' },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  label: { fontSize: 14, color: '#555' },
  value: { fontSize: 14, color: '#222', fontWeight: '600' },
  strong: { fontSize: 16, fontWeight: '700', color: '#222' },
  strongValue: { fontSize: 16, fontWeight: '700', color: '#0a7ea4' },
});
