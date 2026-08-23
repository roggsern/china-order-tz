import { StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  enabled: boolean;
  checkoutSessionId?: string | null;
};

/**
 * Navigates to the shared payment selector with checkout session context.
 */
export function ContinueToPaymentButton({ enabled, checkoutSessionId }: Props) {
  return (
    <View style={styles.wrap}>
      <PrimaryButton
        label="Continue to Payment"
        disabled={!enabled || !checkoutSessionId}
        onPress={() =>
          router.push(
            `/(app)/payment?checkoutSessionId=${encodeURIComponent(checkoutSessionId!)}`,
          )
        }
        style={styles.button}
      />
      <Text style={styles.note}>
        Choose a payment method. Payment is confirmed by the server only.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.lg,
    paddingTop: spacing.lg,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  button: { alignSelf: 'stretch' },
  note: {
    marginTop: spacing.sm,
    ...typography.caption,
    textAlign: 'center',
  },
});
