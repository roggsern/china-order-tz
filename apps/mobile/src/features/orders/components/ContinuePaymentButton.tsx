import { StyleSheet } from 'react-native';
import { router } from 'expo-router';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { spacing } from '@/src/shared/theme';
import { buildPaymentHref } from '@/src/features/payments/utils/paymentRoutes';

type Props = {
  orderId: string;
  enabled: boolean;
};

/**
 * Resume NMB payment for unpaid orders — reuses PaymentScreen(orderId).
 */
export function ContinuePaymentButton({ orderId, enabled }: Props) {
  if (!enabled) return null;

  return (
    <PrimaryButton
      label="Continue payment"
      onPress={() => router.push(buildPaymentHref({ orderId }) as never)}
      style={styles.button}
    />
  );
}

const styles = StyleSheet.create({
  button: {
    marginTop: spacing.lg,
    alignSelf: 'stretch',
  },
});
