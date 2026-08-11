import { StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { CatalogProductVariant } from '../models/types';

type Props = {
  variants: CatalogProductVariant[];
  /** Highlight the server-matched / resolved sell unit when known. */
  selectedVariantId?: string | null;
};

/** Lists API variants/configuration rows with server-provided prices/stock. */
export function ProductVariantsList({ variants, selectedVariantId }: Props) {
  if (variants.length === 0) {
    return null;
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Variants</Text>
      {variants.map((variant) => {
        const selected = Boolean(
          selectedVariantId && variant.id === selectedVariantId,
        );
        return (
          <Card
            key={variant.id}
            elevated={false}
            style={[styles.row, selected ? styles.rowSelected : null]}
          >
            <View style={styles.copy}>
              <Text style={styles.name}>
                {variant.name || variant.sku || variant.id}
              </Text>
              {variant.displayAttributes?.length ? (
                <Text style={styles.attrs}>
                  {variant.displayAttributes
                    .map((attr) => `${attr.attribute}: ${attr.value}`)
                    .join(' · ')}
                </Text>
              ) : null}
              <View style={styles.badgeRow}>
                {selected ? (
                  <Badge label="Selected" tone="brand" style={styles.stockBadge} />
                ) : null}
                {variant.inStock != null ? (
                  <Badge
                    label={variant.inStock ? 'Available' : 'Out of stock'}
                    tone={variant.inStock ? 'success' : 'neutral'}
                    style={styles.stockBadge}
                  />
                ) : null}
              </View>
            </View>
            <PriceText value={variant.price} style={styles.price} />
          </Card>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.xxl,
    gap: spacing.sm,
  },
  title: {
    ...typography.title,
    fontSize: 16,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  rowSelected: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryMuted,
  },
  copy: {
    flex: 1,
    gap: spacing.xs,
  },
  name: {
    ...typography.bodyStrong,
  },
  attrs: {
    ...typography.caption,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.xs,
  },
  stockBadge: {
    alignSelf: 'flex-start',
  },
  price: {
    fontSize: 14,
  },
});
