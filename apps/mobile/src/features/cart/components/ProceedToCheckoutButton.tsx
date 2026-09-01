import { StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  disabled?: boolean;
  quantityBlocked?: boolean;
};

/**
 * Opens the real checkout flow (M3.8).
 * Payment is intentionally not started here.
 */
export function ProceedToCheckoutButton({ disabled, quantityBlocked }: Props) {
  const blocked = Boolean(quantityBlocked);

  return (
    <View style={styles.wrap}>
      {blocked ? (
        <Text style={styles.blocked} accessibilityLiveRegion="polite">
          Update quantities to meet purchase requirements before checkout.
        </Text>
      ) : null}
      <PrimaryButton
        label="Proceed to Checkout"
        disabled={disabled || blocked}
        onPress={() => {
          if (blocked) return;
          router.push('/(app)/checkout');
        }}
        style={styles.button}
      />
      <Text style={styles.note}>
        Review totals and shipping before payment.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.sm,
    paddingTop: spacing.lg,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  button: {
    alignSelf: 'stretch',
  },
  blocked: {
    marginBottom: spacing.sm,
    ...typography.body,
    color: colors.warning,
    textAlign: 'center',
    flexShrink: 1,
  },
  note: {
    marginTop: spacing.sm,
    ...typography.caption,
    textAlign: 'center',
  },
});
