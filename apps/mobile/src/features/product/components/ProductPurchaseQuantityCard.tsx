import { StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  resolvePdpPurchaseQuantityView,
  type PurchaseQuantityPresentation,
} from '@/src/features/purchasing/purchaseQuantity';

type Props = {
  presentation: PurchaseQuantityPresentation | null;
};

export function ProductPurchaseQuantityCard({ presentation }: Props) {
  const view = resolvePdpPurchaseQuantityView(presentation);
  if (!view) {
    return null;
  }

  return (
    <View
      style={styles.wrap}
      accessibilityRole="summary"
      accessibilityLiveRegion="polite"
      accessibilityLabel="Purchase requirements"
    >
      <Text style={styles.eyebrow}>Purchase requirements</Text>
      <Text style={styles.title}>{view.minimumLabel}</Text>
      {view.incrementLabel ? (
        <Text style={styles.body}>{view.incrementLabel}</Text>
      ) : null}
      {view.allowedExample ? (
        <Text style={styles.note}>{view.allowedExample}</Text>
      ) : null}
      {view.status ? (
        <Text style={view.incomplete ? styles.warning : styles.quiet}>
          {view.status}
        </Text>
      ) : null}
      {view.nextAllowed ? (
        <Text style={styles.warning}>{view.nextAllowed}</Text>
      ) : null}
      {view.mixVariants ? (
        <Text style={styles.note}>{view.mixVariants}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.md,
    padding: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  eyebrow: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: colors.textMuted,
    marginBottom: spacing.xs,
  },
  title: {
    ...typography.bodyStrong,
    flexShrink: 1,
  },
  body: {
    marginTop: spacing.xs,
    ...typography.body,
    flexShrink: 1,
  },
  note: {
    marginTop: spacing.xs,
    ...typography.caption,
    flexShrink: 1,
  },
  warning: {
    marginTop: spacing.sm,
    ...typography.body,
    color: colors.warning,
    fontWeight: '600',
    flexShrink: 1,
  },
  quiet: {
    marginTop: spacing.sm,
    ...typography.body,
    color: colors.textSecondary,
    flexShrink: 1,
  },
});
