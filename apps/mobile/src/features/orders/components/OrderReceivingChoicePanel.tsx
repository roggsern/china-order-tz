import { useState } from 'react';
import { Pressable, StyleSheet, Text } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { useSelectReceivingMethodMutation } from '../hooks/useOrders';
import type { LastMileReceivingMethod, ReceivingChoiceSnapshot } from '../models/types';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import { shouldShowReceivingSelector } from '../utils/mapOrders';

type Props = {
  orderId: string;
  orderStatus?: string | null;
  receivingChoice: ReceivingChoiceSnapshot | null | undefined;
  onUpdated?: () => void;
};

const RECEIVING_OPTIONS: {
  value: LastMileReceivingMethod;
  label: string;
  description: string;
}[] = [
  {
    value: 'self_pickup',
    label: 'Self pickup',
    description: 'Collect your order from CHINA ORDER TZ.',
  },
  {
    value: 'negotiated_delivery',
    label: 'Arrange delivery',
    description: 'Ask CHINA ORDER TZ to arrange delivery. Fee is confirmed by the server.',
  },
];

export function OrderReceivingChoicePanel({
  orderId,
  orderStatus,
  receivingChoice,
  onUpdated,
}: Props) {
  const mutation = useSelectReceivingMethodMutation(orderId);
  const [error, setError] = useState<string | null>(null);

  if (!receivingChoice?.eligible && !receivingChoice?.selectedMethod) {
    return null;
  }

  const offerSelect = shouldShowReceivingSelector(receivingChoice, orderStatus);

  async function selectMethod(method: LastMileReceivingMethod) {
    setError(null);
    try {
      await mutation.mutateAsync(method);
      onUpdated?.();
    } catch (err) {
      setError(getOrderErrorMessage(err));
    }
  }

  return (
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Receive your order</Text>
      {receivingChoice.selectedMethod ? (
        <Text style={styles.selected}>
          Selected:{' '}
          {receivingChoice.selectedMethodLabel ??
            (receivingChoice.selectedMethod === 'self_pickup'
              ? 'Self pickup'
              : 'Arrange delivery')}
        </Text>
      ) : offerSelect ? (
        <>
          <Text style={styles.note}>
            Your order has arrived in Tanzania. Choose how you would like to receive it.
          </Text>
          {RECEIVING_OPTIONS.map((option) => (
            <Pressable
              key={option.value}
              accessibilityRole="button"
              disabled={mutation.isPending}
              onPress={() => void selectMethod(option.value)}
              style={styles.option}
            >
              <Text style={styles.optionLabel}>
                {mutation.isPending && mutation.variables === option.value
                  ? 'Saving…'
                  : option.label}
              </Text>
              <Text style={styles.optionNote}>{option.description}</Text>
            </Pressable>
          ))}
        </>
      ) : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.lg,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  title: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  note: {
    ...typography.caption,
    marginBottom: spacing.md,
  },
  selected: {
    ...typography.bodyStrong,
    color: colors.primaryPressed,
  },
  option: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    marginBottom: spacing.sm,
    backgroundColor: colors.surface,
  },
  optionLabel: {
    ...typography.bodyStrong,
    color: colors.text,
  },
  optionNote: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
  error: {
    ...typography.caption,
    color: colors.error,
    marginTop: spacing.xs,
  },
});
