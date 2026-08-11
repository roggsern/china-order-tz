import { StyleSheet, Text, View } from 'react-native';
import type { CheckoutItem } from '../models/types';
import { formatCheckoutMoney } from '../utils/mapCheckout';

type Props = {
  items: CheckoutItem[];
  currency?: string;
};

export function CheckoutItemsList({ items, currency = 'TZS' }: Props) {
  if (items.length === 0) {
    return <Text style={styles.empty}>No items in checkout.</Text>;
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Items</Text>
      {items.map((item) => (
        <View key={item.id} style={styles.row}>
          <View style={styles.copy}>
            <Text style={styles.name}>{item.productName}</Text>
            <Text style={styles.meta}>
              Qty {item.quantity}
              {item.source ? ` · ${item.source}` : ''}
            </Text>
            {item.shippingMethod ? (
              <Text style={styles.meta}>
                Shipping: {item.shippingMethod}{' '}
                {item.shippingSubtotal != null
                  ? `(${formatCheckoutMoney(item.shippingSubtotal, currency)})`
                  : ''}
              </Text>
            ) : null}
            {item.deliveryStatus ? (
              <Text style={styles.meta}>{item.deliveryStatus}</Text>
            ) : null}
          </View>
          <View style={styles.prices}>
            <Text style={styles.unit}>
              {formatCheckoutMoney(item.unitPrice, currency)}
            </Text>
            <Text style={styles.line}>
              {formatCheckoutMoney(item.lineSubtotal, currency)}
            </Text>
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 8 },
  title: { fontSize: 16, fontWeight: '700', marginBottom: 8, color: '#222' },
  empty: { color: '#666', fontSize: 14 },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#ddd',
  },
  copy: { flex: 1 },
  name: { fontSize: 14, fontWeight: '600', color: '#222' },
  meta: { marginTop: 2, fontSize: 12, color: '#666' },
  prices: { alignItems: 'flex-end' },
  unit: { fontSize: 12, color: '#555' },
  line: { marginTop: 2, fontSize: 14, fontWeight: '700', color: '#0a7ea4' },
});
