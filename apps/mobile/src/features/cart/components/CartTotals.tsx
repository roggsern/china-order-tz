import { StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { Cart } from '../models/types';

type Props = {
  cart: Cart;
};

/** Displays server-provided cart totals only. */
export function CartTotals({ cart }: Props) {
  return (
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Cart totals</Text>
      <View style={styles.row}>
        <Text style={styles.label}>Items</Text>
        <Text style={styles.value}>{cart.itemCount}</Text>
      </View>
      <View style={styles.row}>
        <Text style={styles.label}>Subtotal</Text>
        <PriceText value={cart.subtotal} currency={cart.currency} style={styles.valuePrice} />
      </View>
      <View style={[styles.row, styles.totalRow]}>
        <Text style={styles.totalLabel}>Total</Text>
        <PriceText
          value={cart.total}
          currency={cart.currency}
          size="large"
          accessibilityLabelPrefix="Total"
        />
      </View>
      <Text style={styles.shippingNote}>
        Bulk discounts apply to product prices only. Shipping is calculated separately.
      </Text>
    </Card>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.sm,
    marginBottom: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  title: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.md,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  totalRow: {
    marginTop: spacing.xs,
    paddingTop: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
    marginBottom: 0,
  },
  label: {
    ...typography.body,
  },
  value: {
    ...typography.bodyStrong,
  },
  valuePrice: {
    fontSize: 14,
    color: colors.text,
  },
  totalLabel: {
    ...typography.title,
    fontSize: 16,
  },
  shippingNote: {
    marginTop: spacing.sm,
    ...typography.caption,
  },
});
