import { useState } from 'react';
import { Alert, StyleSheet, Text } from 'react-native';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';
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
      'This cannot be undone once cancelled.',
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
      <SecondaryButton
        label="Cancel order"
        loading={cancelMutation.isPending}
        disabled={cancelMutation.isPending}
        onPress={confirmCancel}
        style={styles.button}
      />
      {localError ? <Text style={styles.error}>{localError}</Text> : null}
    </>
  );
}

const styles = StyleSheet.create({
  button: {
    marginTop: spacing.lg,
    alignSelf: 'stretch',
    borderColor: colors.error,
  },
  error: {
    marginTop: spacing.sm,
    ...typography.caption,
    color: colors.error,
  },
});
