import { StyleSheet, Text, View } from 'react-native';
import { colors, spacing, typography } from '@/src/shared/theme';

export type CheckoutProgressStep = 'review' | 'shipping' | 'payment';

type Props = {
  current: CheckoutProgressStep;
};

const STEPS: { key: CheckoutProgressStep; label: string }[] = [
  { key: 'review', label: 'Review' },
  { key: 'shipping', label: 'Shipping' },
  { key: 'payment', label: 'Payment' },
];

function stepIndex(step: CheckoutProgressStep): number {
  return STEPS.findIndex((entry) => entry.key === step);
}

/**
 * Visual checkout progress only — does not change checkout/payment logic.
 */
export function CheckoutProgress({ current }: Props) {
  const activeIndex = stepIndex(current);

  return (
    <View style={styles.wrap} accessibilityRole="summary">
      {STEPS.map((step, index) => {
        const active = index === activeIndex;
        const done = index < activeIndex;
        return (
          <View key={step.key} style={styles.step}>
            <View
              style={[
                styles.dot,
                done || active ? styles.dotActive : null,
              ]}
            />
            <Text
              style={[
                styles.label,
                active ? styles.labelActive : null,
                done ? styles.labelDone : null,
              ]}
            >
              {step.label}
            </Text>
            {index < STEPS.length - 1 ? <View style={styles.connector} /> : null}
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.lg,
    gap: spacing.xs,
  },
  step: {
    flexDirection: 'row',
    alignItems: 'center',
    flexShrink: 1,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.borderStrong,
    marginRight: spacing.xs,
  },
  dotActive: {
    backgroundColor: colors.primary,
  },
  label: {
    ...typography.caption,
    fontWeight: '600',
    color: colors.textSubtle,
  },
  labelActive: {
    color: colors.primaryPressed,
    fontWeight: '700',
  },
  labelDone: {
    color: colors.textMuted,
  },
  connector: {
    width: 18,
    height: 1,
    backgroundColor: colors.border,
    marginHorizontal: spacing.sm,
  },
});
