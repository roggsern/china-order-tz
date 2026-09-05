import { StyleSheet, Text, View } from 'react-native';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  parseVolumeMoney,
  volumePricingUnlocked,
  type VolumePricing,
} from '@/src/features/pricing/mapVolumePricing';
import {
  nextTierHelperMessage,
  presentVolumePricingRows,
} from '@/src/features/pricing/presentVolumePricing';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';

type Props = {
  pricing: VolumePricing | null;
  quantity?: number;
  loading?: boolean;
  error?: boolean;
  showVariantAggregationNote?: boolean;
  showShippingNote?: boolean;
  showCartAuthorityNote?: boolean;
};

export function BulkPricingCard({
  pricing,
  quantity,
  loading = false,
  error = false,
  showVariantAggregationNote = false,
  showShippingNote = false,
  showCartAuthorityNote = false,
}: Props) {
  if (loading && !pricing) {
    return (
      <View
        style={styles.wrap}
        accessibilityRole="summary"
        accessibilityLabel="Volume pricing"
      >
        <Text style={styles.title}>Volume pricing</Text>
        <Text style={styles.muted}>Checking volume prices…</Text>
      </View>
    );
  }

  if (error && !pricing) {
    return (
      <View
        style={styles.wrap}
        accessibilityRole="summary"
        accessibilityLabel="Volume pricing"
      >
        <Text style={styles.title}>Volume pricing</Text>
        <Text style={styles.note}>
          Volume prices unavailable. Final price is confirmed in cart.
        </Text>
      </View>
    );
  }

  if (!pricing || pricing.tiers.length === 0) return null;

  const rows = presentVolumePricingRows(pricing, quantity);
  if (rows.length === 0) return null;

  const unlocked = volumePricingUnlocked(pricing);
  const next = nextTierHelperMessage(pricing, quantity);
  const savings = parseVolumeMoney(pricing.savings_total);
  const qty = quantity ?? pricing.eligible_quantity;
  const showUnlocked = unlocked && qty === pricing.eligible_quantity;

  return (
    <View
      style={styles.wrap}
      accessibilityRole="summary"
      accessibilityLabel="Volume pricing"
    >
      <Text style={styles.title}>Volume pricing</Text>
      {rows.map((row) => (
        <View key={row.key} style={styles.tierRow}>
          <Text style={[styles.tierQty, row.active ? styles.tierActive : null]}>
            {row.quantityLabel}
          </Text>
          <View style={styles.priceCol}>
            <PriceText
              value={row.unitPrice}
              currency={pricing.currency}
              style={row.active ? styles.tierActivePrice : styles.tierPriceValue}
              accessibilityLabelPrefix={row.active ? 'Active unit price' : 'Unit price'}
            />
            <Text style={[styles.each, row.active ? styles.tierActive : null]}>
              each
            </Text>
          </View>
        </View>
      ))}

      {showUnlocked ? (
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

      {showCartAuthorityNote ? (
        <Text style={styles.note}>Final price is confirmed in cart.</Text>
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
    gap: spacing.md,
  },
  tierQty: {
    ...typography.body,
    flexShrink: 1,
  },
  priceCol: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: spacing.xs,
  },
  each: {
    ...typography.caption,
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
  muted: {
    ...typography.body,
    color: colors.textMuted,
  },
  note: {
    marginTop: spacing.xs,
    ...typography.caption,
  },
});
