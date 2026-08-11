import { StyleSheet, Text, View } from 'react-native';
import type { Cart } from '../models/types';
import { formatCartMoney } from '../utils/mapCart';

type Props = {
  cart: Cart;
};

/** Displays server-provided cart totals only. */
export function CartTotals({ cart }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Cart totals</Text>
      <View style={styles.row}>
        <Text style={styles.label}>Items</Text>
        <Text style={styles.value}>{cart.itemCount}</Text>
      </View>
      <View style={styles.row}>
        <Text style={styles.label}>Subtotal</Text>
        <Text style={styles.value}>
          {formatCartMoney(cart.subtotal, cart.currency)}
        </Text>
      </View>
      <View style={styles.row}>
        <Text style={styles.totalLabel}>Total</Text>
        <Text style={styles.totalValue}>
          {formatCartMoney(cart.total, cart.currency)}
        </Text>
      </View>
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
  title: {
    fontSize: 15,
    fontWeight: '700',
    marginBottom: 10,
    color: '#222',
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  label: {
    fontSize: 14,
    color: '#555',
  },
  value: {
    fontSize: 14,
    color: '#222',
    fontWeight: '600',
  },
  totalLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: '#222',
  },
  totalValue: {
    fontSize: 16,
    fontWeight: '700',
    color: '#0a7ea4',
  },
});
