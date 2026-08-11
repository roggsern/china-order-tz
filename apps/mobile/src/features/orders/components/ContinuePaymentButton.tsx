import { Pressable, StyleSheet, Text } from 'react-native';
import { router } from 'expo-router';
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
    <Pressable
      style={styles.button}
      onPress={() =>
        router.push(buildPaymentHref({ orderId }) as never)
      }
      accessibilityRole="button"
    >
      <Text style={styles.buttonText}>Continue payment</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    marginTop: 16,
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
