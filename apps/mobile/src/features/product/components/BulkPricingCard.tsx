import { StyleSheet, Text, View } from 'react-native';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  parseVolumeMoney,
  remainingToNextTier,
  volumePricingUnlocked,
  type VolumePricing,
} from '@/src/features/pricing/mapVolumePricing';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';

type Props = {
  pricing: VolumePricing | null;
  showVariantAggregationNote?: boolean;
  showShippingNote?: boolean;
};

function nextMessage(pricing: VolumePricing): string | null {
  const remaining = remainingToNextTier(pricing);
  if (remaining == null || !pricing.next_tier) return null;
  const more = remaining === 1 ? '1 more' : `${remaining} more`;
  if (pricing.next_tier.type === 'percent_off' && pricing.next_tier.discount_percent) {
    return `Add ${more} to unlock ${parseFloat(pricing.next_tier.discount_percent)}% off`;
  }
  return `Add ${more} to unlock ${formatCustomerMoney(pricing.next_tier.unit_price, pricing.currency)} each`;
}

export function BulkPricingCard({
  pricing,
  showVariantAggregationNote = false,
  showShippingNote = false,
}: Props) {
  if (!pricing || pricing.tiers.length === 0) return null;

  const unlocked = volumePricingUnlocked(pricing);
  const next = nextMessage(pricing);
  const savings = parseVolumeMoney(pricing.savings_total);

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Buy More, Save More</Text>
      {pricing.tiers.map((tier) => {
        const active = pricing.current_tier?.min_quantity === tier.min_quantity;
        return (
          <View key={`${tier.scope}-${tier.min_quantity}`} style={styles.tierRow}>
            <Text style={[styles.tierQty, active ? styles.tierActive : null]}>
              {tier.min_quantity}+ pcs
            </Text>
            {tier.type === 'percent_off' && tier.discount_percent ? (
              <Text style={[styles.tierPrice, active ? styles.tierActive : null]}>
                {parseFloat(tier.discount_percent)}% off
              </Text>
            ) : (
              <PriceText
                value={tier.unit_price}
                currency={pricing.currency}
                style={active ? styles.tierActivePrice : styles.tierPriceValue}
              />
            )}
          </View>
        );
      })}

      {unlocked ? (
        <Text style={styles.unlocked}>
          {savings > 0.001
            ? `Bulk price unlocked — you save ${formatCustomerMoney(pricing.savings_total, pricing.currency)}`
            : 'Bulk price unlocked'}
        </Text>
      ) : null}

      {next ? <Text style={styles.next}>{next}</Text> : null}

      {showVariantAggregationNote ? (
        <Text style={styles.note}>
          Different variants of this product count together toward bulk pricing.
        </Text>
      ) : null}

      {showShippingNote ? (
        <Text style={styles.note}>
          Bulk discounts apply to product prices only. Shipping is calculated separately.
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.md,
    marginBottom: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.backgroundMuted,
  },
  title: {
    ...typography.bodyStrong,
    marginBottom: spacing.sm,
  },
  tierRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    marginBottom: spacing.xs,
  },
  tierQty: {
    ...typography.body,
  },
  tierPrice: {
    ...typography.body,
  },
  tierPriceValue: {
    fontSize: 14,
    color: colors.text,
  },
  tierActive: {
    color: colors.success,
    fontWeight: '700',
  },
  tierActivePrice: {
    fontSize: 14,
    color: colors.success,
    fontWeight: '700',
  },
  unlocked: {
    marginTop: spacing.sm,
    ...typography.bodyStrong,
    color: colors.success,
  },
  next: {
    marginTop: spacing.xs,
    ...typography.body,
    color: colors.warning,
  },
  note: {
    marginTop: spacing.xs,
    ...typography.caption,
  },
});
