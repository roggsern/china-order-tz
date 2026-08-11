import { StyleSheet, Text, View } from 'react-native';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { OrderDetailItem } from '../models/types';

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
        <PriceText
          value={item.lineTotal}
          currency={lineCurrency}
          style={styles.lineTotal}
          accessibilityLabelPrefix="Line total"
        />
        {item.unitPrice != null ? (
          <PriceText
            value={item.unitPrice}
            currency={lineCurrency}
            style={styles.unit}
            accessibilityLabelPrefix="Unit price"
          />
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: spacing.md,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  main: { flex: 1 },
  name: { ...typography.bodyStrong },
  meta: { marginTop: spacing.xxs, ...typography.caption },
  prices: { alignItems: 'flex-end', gap: spacing.xxs },
  lineTotal: { fontSize: 14 },
  unit: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSubtle,
  },
});
