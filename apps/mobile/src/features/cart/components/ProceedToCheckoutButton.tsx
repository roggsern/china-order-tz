import { StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  disabled?: boolean;
};

/**
 * Opens the real checkout flow (M3.8).
 * Payment is intentionally not started here.
 */
export function ProceedToCheckoutButton({ disabled }: Props) {
  return (
    <View style={styles.wrap}>
      <PrimaryButton
        label="Proceed to Checkout"
        disabled={disabled}
        onPress={() => router.push('/(app)/checkout')}
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
  note: {
    marginTop: spacing.sm,
    ...typography.caption,
    textAlign: 'center',
  },
});
