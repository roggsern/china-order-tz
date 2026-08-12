import { Pressable, StyleSheet, Text, View } from 'react-native';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import { ContinuePaymentButton } from './ContinuePaymentButton';
import { OrderThumbnail } from './OrderThumbnail';
import type { OrderListItem } from '../models/types';
import { isOrderPayableFromServer } from '../utils/isOrderPayable';
import {
  buildOrderListCardPresentation,
  formatOrderListProductTitle,
} from '../utils/orderCardPresentation';

type Props = {
  order: OrderListItem;
  onPress: () => void;
};

export function OrderListCard({ order, onPress }: Props) {
  const presentation = buildOrderListCardPresentation(order);
  const productTitle = formatOrderListProductTitle(presentation);
  const offerContinuePayment = isOrderPayableFromServer(order);

  return (
    <Card elevated style={styles.card}>
      <Pressable onPress={onPress} accessibilityRole="button">
        <View style={styles.mainRow}>
          <OrderThumbnail
            imageUrl={presentation.imageUrl}
            extraBadge={
              presentation.isMultiItem ? presentation.extraItems : null
            }
            size={76}
          />
          <View style={styles.copy}>
            {productTitle ? (
              <Text style={styles.productName} numberOfLines={2}>
                {productTitle}
              </Text>
            ) : (
              <Text style={styles.productName} numberOfLines={2}>
                Order items
              </Text>
            )}

            <Text style={styles.orderNumber} numberOfLines={1}>
              {presentation.orderNumber}
            </Text>

            <View style={styles.badgeRow}>
              <Badge label={presentation.statusLabel} tone="neutral" />
              {presentation.journeyLabel ? (
                <Badge
                  label={presentation.journeyLabel}
                  tone={
                    presentation.journeyLabel.toLowerCase().includes('tanzania') ||
                    presentation.journeyLabel.toLowerCase().includes('tz')
                      ? 'success'
                      : 'brand'
                  }
                />
              ) : null}
            </View>

            {presentation.createdAt ? (
              <Text style={styles.meta}>
                {formatCustomerDateTime(presentation.createdAt)}
              </Text>
            ) : null}

            {presentation.paymentStatus ? (
              <Text style={styles.meta}>
                Payment: {presentation.paymentStatus}
              </Text>
            ) : null}

            <PriceText
              value={presentation.grandTotal}
              currency={presentation.currency}
              style={styles.total}
              accessibilityLabelPrefix="Order total"
            />
          </View>
        </View>
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
  mainRow: {
    flexDirection: 'row',
    gap: spacing.md,
    alignItems: 'flex-start',
  },
  copy: { flex: 1, minWidth: 0 },
  productName: {
    ...typography.bodyStrong,
    color: colors.text,
  },
  orderNumber: {
    marginTop: spacing.xxs,
    ...typography.caption,
    color: colors.textSecondary,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  meta: { marginTop: spacing.xs, ...typography.caption },
  total: { marginTop: spacing.sm, fontSize: 15 },
});
