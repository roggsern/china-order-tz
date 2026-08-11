import { StyleSheet, Text, View } from 'react-native';
import type { OrderDetailItem } from '../models/types';
import { formatOrderMoney } from '../utils/mapOrders';

type Props = {
  item: OrderDetailItem;
  currency: string;
};

export function OrderItemRow({ item, currency }: Props) {
  const lineCurrency = item.currency ?? currency;

  return (
    <View style={styles.row}>
      <View style={styles.main}>
        <Text style={styles.name}>{item.productName}</Text>
        {item.variantName ? (
          <Text style={styles.meta}>{item.variantName}</Text>
        ) : null}
        {item.attributes.map((attr) => (
          <Text key={`${attr.attribute}:${attr.value}`} style={styles.meta}>
            {attr.attribute}: {attr.value}
          </Text>
        ))}
        <Text style={styles.meta}>Qty {item.quantity}</Text>
      </View>
      <View style={styles.prices}>
        <Text style={styles.lineTotal}>
          {formatOrderMoney(item.lineTotal, lineCurrency)}
        </Text>
        {item.unitPrice != null ? (
          <Text style={styles.unit}>
            {formatOrderMoney(item.unitPrice, lineCurrency)} each
          </Text>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e5e7eb',
  },
  main: { flex: 1 },
  name: { fontSize: 15, fontWeight: '600', color: '#111' },
  meta: { marginTop: 3, fontSize: 13, color: '#666' },
  prices: { alignItems: 'flex-end' },
  lineTotal: { fontSize: 14, fontWeight: '700', color: '#111' },
  unit: { marginTop: 3, fontSize: 12, color: '#777' },
});
