import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';

type Props = {
  enabled: boolean;
  checkoutSessionId?: string | null;
};

/**
 * Navigates to real NMB payment flow with checkout session context.
 */
export function ContinueToPaymentButton({ enabled, checkoutSessionId }: Props) {
  return (
    <View style={styles.wrap}>
      <Pressable
        style={[styles.button, !enabled || !checkoutSessionId ? styles.disabled : null]}
        disabled={!enabled || !checkoutSessionId}
        onPress={() =>
          router.push(
            `/(app)/payment?checkoutSessionId=${encodeURIComponent(checkoutSessionId!)}`,
          )
        }
      >
        <Text style={styles.buttonText}>Continue to Payment</Text>
      </Pressable>
      <Text style={styles.note}>
        Opens NMB Hosted Checkout. Payment success is confirmed by the server only.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 16 },
  button: {
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  disabled: { backgroundColor: '#9bbdca' },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 16 },
  note: {
    marginTop: 8,
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
  },
});
