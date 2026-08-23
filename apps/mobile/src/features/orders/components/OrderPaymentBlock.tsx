import { StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { OrderPaymentSnapshot } from '../models/types';
import type { PaymentDisplayStatus } from '../utils/orderLifecycleDisplay';
import { orderDisplayTone } from '../utils/orderLifecycleDisplay';

type Props = {
  payment: OrderPaymentSnapshot;
  display: PaymentDisplayStatus;
  orderStatus?: string | null;
};

export function OrderPaymentBlock({ payment, display, orderStatus }: Props) {
  const currency = payment.currency ?? 'TZS';
  const methodPrefix =
    orderStatus === 'cancelled' && display.methodLabel
      ? 'Previous method'
      : 'Method';

  return (
    <Card elevated={false} style={styles.block}>
      <Text style={styles.title}>Payment</Text>
      <Badge
        label={display.label}
        tone={orderDisplayTone(display.key)}
        style={styles.badge}
      />
      {display.methodLabel ? (
        <Text style={styles.line}>
          {methodPrefix}: {display.methodLabel}
        </Text>
      ) : payment.paymentMethod || payment.provider ? (
        <Text style={styles.line}>
          Method: {payment.paymentMethod ?? payment.provider}
        </Text>
      ) : null}
      {payment.reference ? (
        <Text style={styles.line}>Reference: {payment.reference}</Text>
      ) : null}
      {payment.amount != null ? (
        <View style={styles.amountRow}>
          <Text style={styles.line}>Amount</Text>
          <PriceText
            value={payment.amount}
            currency={currency}
            accessibilityLabelPrefix="Payment amount"
          />
        </View>
      ) : null}
      {payment.paidAt ? (
        <Text style={styles.line}>Paid at: {payment.paidAt}</Text>
      ) : null}
    </Card>
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
    marginBottom: spacing.sm,
  },
  badge: {
    alignSelf: 'flex-start',
    marginBottom: spacing.sm,
  },
  line: {
    ...typography.body,
    marginBottom: spacing.xs,
  },
  amountRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.xs,
  },
});
