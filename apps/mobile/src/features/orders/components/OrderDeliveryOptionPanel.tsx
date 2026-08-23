import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { canSubmitInFlightAction } from '@/src/core/async/inFlightGuard';
import { Card } from '@/src/shared/ui/Card';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  useOrderDeliveryOption,
  useSelectDeliveryOptionMutation,
  useUpdateDeliveryOptionMutation,
} from '../hooks/useOrders';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import {
  canManagePostPayDeliveryOption,
  isDeliveryOptionLocked,
} from '../utils/mapDeliveryOption';

type Props = {
  orderId: string;
  orderStatus: string | null;
  paymentStatus: string | null;
};

export function OrderDeliveryOptionPanel({
  orderId,
  orderStatus,
  paymentStatus,
}: Props) {
  const query = useOrderDeliveryOption(orderId);
  const selectMutation = useSelectDeliveryOptionMutation(orderId);
  const updateMutation = useUpdateDeliveryOptionMutation(orderId);
  const [deliveryType, setDeliveryType] = useState<string | null>(null);
  const [shippingMethod, setShippingMethod] = useState<string | null>(null);
  const [agentName, setAgentName] = useState('');
  const [agentContact, setAgentContact] = useState('');
  const [notes, setNotes] = useState('');
  const [error, setError] = useState<string | null>(null);

  const option = query.data?.deliveryOption ?? null;
  const available = query.data?.available ?? null;
  const canSelect = canManagePostPayDeliveryOption({
    status: orderStatus,
    paymentStatus,
  });
  const locked = isDeliveryOptionLocked(option);
  const showForm = canSelect && !locked;
  const busy = selectMutation.isPending || updateMutation.isPending;
  const selectedType = option?.deliveryType ?? deliveryType ?? available?.deliveryTypes[0]?.value ?? '';

  if (query.isLoading && !query.data) {
    return (
      <Card elevated={false} style={styles.wrap}>
        <Text style={styles.title}>Delivery handoff</Text>
        <Text style={styles.note}>Loading delivery option…</Text>
      </Card>
    );
  }

  if (!available && !option) {
    return null;
  }

  async function saveOption() {
    if (!canSubmitInFlightAction(busy)) return;
    setError(null);
    try {
      if (option) {
        await updateMutation.mutateAsync({
          agentName: selectedType === 'customer_agent' ? agentName : option.agentName,
          agentContact:
            selectedType === 'customer_agent' ? agentContact : option.agentContact,
          notes,
        });
      } else if (selectedType) {
        await selectMutation.mutateAsync({
          deliveryType: selectedType,
          shippingMethod:
            selectedType === 'company_shipping' ? shippingMethod : null,
          agentName: selectedType === 'customer_agent' ? agentName : null,
          agentContact: selectedType === 'customer_agent' ? agentContact : null,
          notes,
        });
      }
    } catch (err) {
      setError(getOrderErrorMessage(err));
    }
  }

  async function confirmOption() {
    if (!canSubmitInFlightAction(busy)) return;
    setError(null);
    try {
      await updateMutation.mutateAsync({ deliveryStatus: 'confirmed' });
    } catch (err) {
      setError(getOrderErrorMessage(err));
    }
  }

  return (
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Delivery handoff</Text>
      <Text style={styles.note}>
        Shipping choice was locked at checkout. This does not change the amount paid.
      </Text>

      {option ? (
        <View style={styles.snapshot}>
          <Text style={styles.optionLabel}>
            {option.deliveryTypeLabel ?? option.deliveryType}
          </Text>
          {option.shippingMethodLabel ? (
            <Text style={styles.note}>{option.shippingMethodLabel}</Text>
          ) : null}
          {option.lastMileReceivingMethodLabel ? (
            <Text style={styles.note}>
              Receiving: {option.lastMileReceivingMethodLabel}
            </Text>
          ) : null}
          {option.agentName ? (
            <Text style={styles.note}>
              Agent: {option.agentName}
              {option.agentContact ? ` · ${option.agentContact}` : ''}
            </Text>
          ) : null}
          <Text style={styles.status}>
            Status: {option.deliveryStatusLabel ?? option.deliveryStatus}
          </Text>
        </View>
      ) : (
        <Text style={styles.note}>
          No delivery option on this order yet.
          {canSelect
            ? ' You can record a legacy handoff below.'
            : ' Complete payment first if this is an older order.'}
        </Text>
      )}

      {showForm && !option && available ? (
        <>
          <Text style={styles.caption}>
            {available.marketLabel || available.market}
          </Text>
          {available.deliveryTypes.map((type) => (
            <Pressable
              key={type.value}
              accessibilityRole="button"
              disabled={busy}
              onPress={() => setDeliveryType(type.value)}
              style={[
                styles.option,
                selectedType === type.value ? styles.optionSelected : null,
              ]}
            >
              <Text style={styles.optionLabel}>{type.label}</Text>
            </Pressable>
          ))}
          {selectedType === 'company_shipping'
            ? available.shippingMethods.map((method) => (
                <Pressable
                  key={method.value}
                  accessibilityRole="button"
                  disabled={busy}
                  onPress={() => setShippingMethod(method.value)}
                  style={[
                    styles.option,
                    shippingMethod === method.value ? styles.optionSelected : null,
                  ]}
                >
                  <Text style={styles.optionLabel}>{method.label}</Text>
                </Pressable>
              ))
            : null}
        </>
      ) : null}

      {showForm && selectedType === 'customer_agent' ? (
        <>
          <TextInput
            value={agentName}
            onChangeText={setAgentName}
            placeholder="Agent name"
            placeholderTextColor={colors.textSecondary}
            style={styles.input}
          />
          <TextInput
            value={agentContact}
            onChangeText={setAgentContact}
            placeholder="Agent contact"
            placeholderTextColor={colors.textSecondary}
            style={styles.input}
          />
        </>
      ) : null}

      {showForm ? (
        <>
          <TextInput
            value={notes}
            onChangeText={setNotes}
            placeholder="Notes (optional)"
            placeholderTextColor={colors.textSecondary}
            style={styles.input}
          />
          <Pressable
            accessibilityRole="button"
            disabled={busy}
            onPress={() => void saveOption()}
            style={styles.option}
          >
            <Text style={styles.optionLabel}>
              {busy ? 'Saving…' : option ? 'Update delivery option' : 'Save delivery option'}
            </Text>
          </Pressable>
          {option?.deliveryStatus === 'pending' ? (
            <Pressable
              accessibilityRole="button"
              disabled={busy}
              onPress={() => void confirmOption()}
              style={styles.option}
            >
              <Text style={styles.optionLabel}>
                {busy ? 'Saving…' : 'Confirm selection'}
              </Text>
            </Pressable>
          ) : null}
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
    marginBottom: spacing.sm,
  },
  caption: {
    ...typography.caption,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  snapshot: {
    marginBottom: spacing.md,
  },
  status: {
    ...typography.caption,
    fontWeight: '700',
    marginTop: spacing.xs,
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
  optionSelected: {
    borderColor: colors.primary,
  },
  optionLabel: {
    ...typography.bodyStrong,
    color: colors.text,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    marginBottom: spacing.sm,
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.surface,
  },
  error: {
    ...typography.caption,
    color: colors.error,
    marginTop: spacing.xs,
  },
});
