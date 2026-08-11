import { StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { OrderSummary } from '../models/types';

type Props = {
  summary: OrderSummary;
  currency: string;
};

export function OrderSummaryBlock({ summary, currency }: Props) {
  return (
    <Card elevated={false} style={styles.block}>
      <Text style={styles.title}>Totals</Text>
      {summary.subtotal != null ? (
        <Row label="Subtotal" value={summary.subtotal} currency={currency} />
      ) : null}
      {summary.shipping != null ? (
        <Row label="Shipping" value={summary.shipping} currency={currency} />
      ) : null}
      {summary.discount != null ? (
        <Row label="Discount" value={summary.discount} currency={currency} />
      ) : null}
      {summary.tax != null ? (
        <Row label="Tax" value={summary.tax} currency={currency} />
      ) : null}
      <View style={styles.grandRow}>
        <Text style={styles.grandLabel}>Total</Text>
        <PriceText
          value={summary.grandTotal}
          currency={currency}
          size="large"
          accessibilityLabelPrefix="Order total"
        />
      </View>
    </Card>
  );
}

function Row({
  label,
  value,
  currency,
}: {
  label: string;
  value: string | number | null | undefined;
  currency: string;
}) {
  return (
    <View style={styles.row}>
      <Text style={styles.line}>{label}</Text>
      <PriceText
        value={value}
        currency={currency}
        style={styles.value}
        accessibilityLabelPrefix={label}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  block: {
    marginTop: spacing.lg,
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
  line: { ...typography.body },
  value: { fontSize: 14, color: colors.text },
  grandRow: {
    marginTop: spacing.xs,
    paddingTop: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  grandLabel: { ...typography.title, fontSize: 16 },
});
