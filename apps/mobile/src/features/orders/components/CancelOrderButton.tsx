import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
} from 'react-native';
import { useCancelOrderMutation } from '../hooks/useOrders';
import {
  getOrderErrorMessage,
  isOrderCancellationRejected,
} from '../utils/orderErrorMessage';

type Props = {
  orderId: string;
  enabled: boolean;
  onCancelled?: () => void;
  onRejected?: (message: string) => void;
};

export function CancelOrderButton({
  orderId,
  enabled,
  onCancelled,
  onRejected,
}: Props) {
  const cancelMutation = useCancelOrderMutation(orderId);
  const [localError, setLocalError] = useState<string | null>(null);

  if (!enabled) {
    return null;
  }

  function confirmCancel() {
    Alert.alert(
      'Cancel order?',
      'This cannot be undone if the server accepts the cancellation.',
      [
        { text: 'Keep order', style: 'cancel' },
        {
          text: 'Cancel order',
          style: 'destructive',
          onPress: () => void runCancel(),
        },
      ],
    );
  }

  async function runCancel() {
    setLocalError(null);
    try {
      await cancelMutation.mutateAsync({});
      onCancelled?.();
    } catch (error) {
      const message = getOrderErrorMessage(error);
      setLocalError(message);
      if (isOrderCancellationRejected(error)) {
        onRejected?.(message);
      }
    }
  }

  return (
    <>
      <Pressable
        style={[styles.button, cancelMutation.isPending ? styles.disabled : null]}
        disabled={cancelMutation.isPending}
        onPress={confirmCancel}
      >
        {cancelMutation.isPending ? (
          <ActivityIndicator color="#b00020" />
        ) : (
          <Text style={styles.buttonText}>Cancel order</Text>
        )}
      </Pressable>
      {localError ? <Text style={styles.error}>{localError}</Text> : null}
    </>
  );
}

const styles = StyleSheet.create({
  button: {
    marginTop: 16,
    borderWidth: 1,
    borderColor: '#b00020',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  buttonText: { color: '#b00020', fontWeight: '700', fontSize: 15 },
  disabled: { opacity: 0.6 },
  error: { marginTop: 8, color: '#b00020', fontSize: 13 },
});
