import { StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  resolveCartBlockerView,
  type PurchaseQuantityBlocker,
} from '@/src/features/purchasing/purchaseQuantity';

type Props = {
  blocker: PurchaseQuantityBlocker | null;
  aggregatesVariants?: boolean;
};

export function CartPurchaseQuantityBanner({
  blocker,
  aggregatesVariants = false,
}: Props) {
  if (!blocker) {
    return null;
  }

  const view = resolveCartBlockerView(blocker, aggregatesVariants);

  return (
    <View
      style={styles.wrap}
      accessibilityRole="summary"
      accessibilityLiveRegion="polite"
      accessibilityLabel="Purchase requirements"
    >
      <Text style={styles.eyebrow}>Purchase requirements</Text>
      <Text style={styles.status}>{view.status}</Text>
      {view.nextAllowed ? (
        <Text style={styles.next}>{view.nextAllowed}</Text>
      ) : null}
      {view.mixVariants ? (
        <Text style={styles.note}>{view.mixVariants}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.warning,
    backgroundColor: colors.warningMuted,
  },
  eyebrow: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: colors.warning,
    marginBottom: spacing.xs,
  },
  status: {
    ...typography.bodyStrong,
    color: colors.text,
    flexShrink: 1,
  },
  next: {
    marginTop: spacing.xs,
    ...typography.body,
    color: colors.warning,
    fontWeight: '600',
    flexShrink: 1,
  },
  note: {
    marginTop: spacing.xs,
    ...typography.caption,
    color: colors.warning,
    flexShrink: 1,
  },
});
