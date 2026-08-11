import { Pressable, StyleSheet, Text, View } from 'react-native';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import { ContinuePaymentButton } from './ContinuePaymentButton';
import type { OrderListItem } from '../models/types';
import { isOrderPayableFromServer } from '../utils/isOrderPayable';

type Props = {
  order: OrderListItem;
  onPress: () => void;
};

export function OrderListCard({ order, onPress }: Props) {
  const previewName = order.preview?.primaryItem?.name;
  const extra = order.preview?.extraItems ?? 0;
  const offerContinuePayment = isOrderPayableFromServer(order);

  return (
    <Card elevated style={styles.card}>
      <Pressable onPress={onPress} accessibilityRole="button">
        <View style={styles.row}>
          <Text style={styles.orderNumber} numberOfLines={1}>
            {order.orderNumber ?? order.id}
          </Text>
          {order.journeyLabel ? (
            <Badge
              label={order.journeyLabel}
              tone={
                order.journeyLabel.toLowerCase().includes('tanzania') ||
                order.journeyLabel.toLowerCase().includes('tz')
                  ? 'success'
                  : 'brand'
              }
            />
          ) : null}
        </View>

        <Text style={styles.status}>
          {order.statusLabel ?? order.status ?? 'Status unavailable'}
        </Text>

        {order.paymentStatus ? (
          <Text style={styles.meta}>Payment: {order.paymentStatus}</Text>
        ) : null}

        {order.createdAt ? (
          <Text style={styles.meta}>{formatCustomerDateTime(order.createdAt)}</Text>
        ) : null}

        {previewName ? (
          <Text style={styles.preview} numberOfLines={1}>
            {previewName}
            {extra > 0 ? ` +${extra} more` : ''}
          </Text>
        ) : null}

        <PriceText
          value={order.grandTotal}
          currency={order.currency ?? 'TZS'}
          style={styles.total}
          accessibilityLabelPrefix="Order total"
        />
      </Pressable>

      <ContinuePaymentButton
        orderId={order.id}
        enabled={offerContinuePayment}
      />
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    marginBottom: spacing.md,
    borderColor: colors.border,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: spacing.sm,
  },
  orderNumber: {
    ...typography.bodyStrong,
    flex: 1,
    color: colors.text,
  },
  status: {
    marginTop: spacing.sm,
    ...typography.label,
    color: colors.text,
  },
  meta: { marginTop: spacing.xs, ...typography.caption },
  preview: { marginTop: spacing.sm, ...typography.caption, color: colors.textSecondary },
  total: { marginTop: spacing.sm, fontSize: 15 },
});
