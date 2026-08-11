import { StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { CheckoutItem } from '../models/types';

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
        <Card key={item.id} elevated={false} style={styles.row}>
          <View style={styles.copy}>
            <Text style={styles.name}>{item.productName}</Text>
            <Text style={styles.meta}>
              Qty {item.quantity}
              {item.source ? ` · ${item.source}` : ''}
            </Text>
            {item.shippingMethod ? (
              <View style={styles.shippingRow}>
                <Text style={styles.meta}>Shipping: {item.shippingMethod}</Text>
                {item.shippingSubtotal != null ? (
                  <PriceText
                    value={item.shippingSubtotal}
                    currency={currency}
                    style={styles.inlinePrice}
                    accessibilityLabelPrefix="Shipping"
                  />
                ) : null}
              </View>
            ) : null}
            {item.deliveryStatus ? (
              <Text style={styles.meta}>{item.deliveryStatus}</Text>
            ) : null}
          </View>
          <View style={styles.prices}>
            <PriceText
              value={item.unitPrice}
              currency={currency}
              style={styles.unit}
              accessibilityLabelPrefix="Unit"
            />
            <PriceText
              value={item.lineSubtotal}
              currency={currency}
              accessibilityLabelPrefix="Line"
            />
          </View>
        </Card>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: spacing.sm, gap: spacing.sm },
  title: {
    ...typography.title,
    fontSize: 16,
    marginBottom: spacing.xs,
  },
  empty: { ...typography.body },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  copy: { flex: 1 },
  name: { ...typography.bodyStrong },
  meta: { marginTop: spacing.xxs, ...typography.caption },
  shippingRow: {
    marginTop: spacing.xxs,
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: spacing.xs,
  },
  prices: { alignItems: 'flex-end', gap: spacing.xxs },
  unit: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textMuted,
  },
  inlinePrice: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textMuted,
  },
});
