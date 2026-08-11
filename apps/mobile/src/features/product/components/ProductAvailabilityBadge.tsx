import { StyleSheet, Text } from 'react-native';
import { Badge, type BadgeTone } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { CatalogProductDetail, ProductConfiguration } from '../models/types';
import { resolveCustomerAvailabilityLabel } from '../utils/resolveCustomerAvailabilityLabel';

type Props = {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
};

function toneForLabel(label: string): BadgeTone {
  if (label === 'Available') return 'success';
  if (label === 'Out of stock' || label === 'Unavailable') return 'error';
  if (label === 'Select options') return 'warning';
  return 'neutral';
}

/** Customer availability from server fields — no conflicting technical flags. */
export function ProductAvailabilityBadge({ product, configuration }: Props) {
  const label = resolveCustomerAvailabilityLabel({ product, configuration });

  return (
    <Card elevated={false} style={styles.wrap}>
      <Badge label={label} tone={toneForLabel(label)} />
      {product.unavailabilityReason ? (
        <Text style={styles.reason}>{product.unavailabilityReason}</Text>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
    gap: spacing.sm,
  },
  reason: {
    ...typography.caption,
    color: colors.error,
  },
});
