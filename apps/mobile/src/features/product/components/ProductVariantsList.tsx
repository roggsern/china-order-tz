import { StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { CatalogProductVariant } from '../models/types';

type Props = {
  variants: CatalogProductVariant[];
};

/** Lists API variants/configuration rows with server-provided prices. */
export function ProductVariantsList({ variants }: Props) {
  if (variants.length === 0) {
    return null;
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Variants</Text>
      {variants.map((variant) => (
        <Card key={variant.id} elevated={false} style={styles.row}>
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
            {variant.inStock != null ? (
              <Badge
                label={variant.inStock ? 'In stock' : 'Out of stock'}
                tone={variant.inStock ? 'success' : 'neutral'}
                style={styles.stockBadge}
              />
            ) : null}
          </View>
          <PriceText value={variant.price} style={styles.price} />
        </Card>
      ))}
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
  stockBadge: {
    alignSelf: 'flex-start',
    marginTop: spacing.xs,
  },
  price: {
    fontSize: 14,
  },
});
