import { StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { CheckoutPrepare, CheckoutSession } from '../models/types';

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
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Totals</Text>
      <Row label="Subtotal" value={subtotal} currency={currency} />
      {shipping != null ? (
        <Row label="Shipping" value={shipping} currency={currency} />
      ) : null}
      {discount != null ? (
        <Row label="Discount" value={discount} currency={currency} />
      ) : null}
      {tax != null ? <Row label="Tax" value={tax} currency={currency} /> : null}
      <Row label="Grand total" value={grand} currency={currency} strong />
    </Card>
  );
}

function Row({
  label,
  value,
  currency,
  strong,
}: {
  label: string;
  value: string | number | null | undefined;
  currency: string;
  strong?: boolean;
}) {
  return (
    <View style={[styles.row, strong ? styles.totalRow : null]}>
      <Text style={[styles.label, strong ? styles.strong : null]}>{label}</Text>
      <PriceText
        value={value}
        currency={currency}
        size={strong ? 'large' : 'default'}
        style={strong ? undefined : styles.value}
        accessibilityLabelPrefix={label}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
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
  totalRow: {
    marginTop: spacing.xs,
    paddingTop: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
    marginBottom: 0,
  },
  label: { ...typography.body },
  value: { fontSize: 14, color: colors.text },
  strong: { ...typography.title, fontSize: 16 },
});
