import { StyleSheet, Text, View } from 'react-native';
import { Badge, type BadgeTone } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { PaymentTransaction } from '../models/types';
import {
  isSuccessfulPaymentStatus,
  paymentStatusLabel,
} from '../utils/mapPayment';

type Props = {
  transaction: PaymentTransaction;
};

function statusTone(status: string | null | undefined): BadgeTone {
  if (isSuccessfulPaymentStatus(status)) return 'success';
  if (status === 'failed' || status === 'cancelled' || status === 'expired') {
    return 'error';
  }
  if (status === 'pending' || status === 'processing') return 'warning';
  return 'info';
}

export function PaymentStatusCard({ transaction }: Props) {
  return (
    <Card elevated style={styles.wrap}>
      <Text style={styles.label}>Payment status</Text>
      <Badge
        label={paymentStatusLabel(transaction.status)}
        tone={statusTone(transaction.status)}
        style={styles.badge}
      />
      <View style={styles.amountRow}>
        <Text style={styles.metaLabel}>Amount</Text>
        <PriceText
          value={transaction.amount}
          currency={transaction.currency ?? 'TZS'}
          size="large"
          accessibilityLabelPrefix="Amount"
        />
      </View>
      {transaction.merchantReference ? (
        <Text style={styles.meta}>Ref: {transaction.merchantReference}</Text>
      ) : null}
      {transaction.order?.orderNumber ? (
        <Text style={styles.meta}>Order: {transaction.order.orderNumber}</Text>
      ) : null}
      <Text style={styles.note}>
        Final status is confirmed by the server after reconciliation and refresh.
      </Text>
    </Card>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  label: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: spacing.sm,
  },
  badge: { alignSelf: 'flex-start', marginBottom: spacing.md },
  amountRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  metaLabel: { ...typography.body },
  meta: { marginTop: spacing.xs, ...typography.caption },
  note: {
    marginTop: spacing.md,
    ...typography.caption,
  },
});
