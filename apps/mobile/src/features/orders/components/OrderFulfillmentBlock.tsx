import { StyleSheet, Text } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { FulfillmentDisplayStatus } from '../utils/orderLifecycleDisplay';
import { orderDisplayTone } from '../utils/orderLifecycleDisplay';

type Props = {
  fulfillment: FulfillmentDisplayStatus;
};

export function OrderFulfillmentBlock({ fulfillment }: Props) {
  return (
    <Card elevated={false} style={styles.block}>
      <Text style={styles.title}>Fulfillment</Text>
      <Badge
        label={fulfillment.label}
        tone={orderDisplayTone(fulfillment.key)}
        style={styles.badge}
      />
      {!fulfillment.showProgression ? (
        <Text style={styles.line}>
          {fulfillment.key === 'cancelled' || fulfillment.key === 'CANCELLED'
            ? 'Shipment is not active for this order.'
            : 'Shipment has not started.'}
        </Text>
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
});
