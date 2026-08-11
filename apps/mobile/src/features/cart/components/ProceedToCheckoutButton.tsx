import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';

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
      <Pressable
        style={[styles.button, disabled ? styles.buttonDisabled : null]}
        disabled={disabled}
        onPress={() => router.push('/(app)/checkout')}
      >
        <Text style={styles.buttonText}>Proceed to Checkout</Text>
      </Pressable>
      <Text style={styles.note}>Review totals and shipping before payment.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 16,
  },
  button: {
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonDisabled: {
    backgroundColor: '#9bbdca',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  note: {
    marginTop: 8,
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
  },
});
