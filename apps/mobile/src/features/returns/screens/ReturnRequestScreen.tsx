import { useMemo, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { canSubmitInFlightAction } from '@/src/core/async/inFlightGuard';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { useOrderDetail } from '@/src/features/orders/hooks/useOrders';
import { buildOrderDetailHref } from '@/src/features/orders/utils/orderRoutes';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { useCreateReturnMutation } from '../hooks/useReturns';
import { RETURN_REASON_OPTIONS } from '../models/types';
import {
  clampReturnQuantity,
  isReturnableOrderItemId,
  isSupportedReturnReason,
  shouldOfferReturnRequest,
} from '../utils/returnEligibility';
import { getReturnErrorMessage } from '../utils/returnErrorMessage';
import { buildReturnDetailHref } from '../utils/returnRoutes';

type Props = {
  orderId: string;
};

type ItemSelection = {
  selected: boolean;
  quantity: number;
};

export function ReturnRequestScreen({ orderId }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const orderQuery = useOrderDetail(orderId);
  const mutation = useCreateReturnMutation(orderId);
  const [reason, setReason] = useState<string>(RETURN_REASON_OPTIONS[0]);
  const [description, setDescription] = useState('');
  const [customerNotes, setCustomerNotes] = useState('');
  const [selections, setSelections] = useState<Record<string, ItemSelection>>({});
  const [error, setError] = useState<string | null>(null);

  const order = orderQuery.data;
  const returnableItems = useMemo(() => {
    if (!order) return [];
    return order.items.filter((item) => isReturnableOrderItemId(item.id));
  }, [order]);

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Request return"
        message="Please sign in to request a return."
        actionLabel="Sign in"
        onActionPress={() =>
          router.push(buildLoginHref(`/(app)/orders/${encodeURIComponent(orderId)}/return`))
        }
        style={styles.fill}
      />
    );
  }

  if (orderQuery.isLoading && !order) {
    return <ScreenLoadingState label="Loading order…" />;
  }

  if (orderQuery.isError && !order) {
    return (
      <EmptyState
        title="Order unavailable"
        message={getReturnErrorMessage(orderQuery.error)}
        actionLabel="Retry"
        onActionPress={() => void orderQuery.refetch()}
        style={styles.fill}
      />
    );
  }

  if (!order) {
    return (
      <EmptyState
        title="Order not found"
        message="This order could not be loaded."
        style={styles.fill}
      />
    );
  }

  if (!shouldOfferReturnRequest(order.status)) {
    return (
      <EmptyState
        title="Return not available"
        message="Returns can be requested after an order is delivered or completed."
        actionLabel="Back to order"
        onActionPress={() => router.replace(buildOrderDetailHref(orderId) as never)}
        style={styles.fill}
      />
    );
  }

  function toggleItem(itemId: string, max: number) {
    setSelections((prev) => ({
      ...prev,
      [itemId]: {
        selected: !prev[itemId]?.selected,
        quantity: prev[itemId]?.quantity ?? clampReturnQuantity(max, max),
      },
    }));
  }

  function setQty(itemId: string, quantity: number, max: number) {
    setSelections((prev) => ({
      ...prev,
      [itemId]: {
        selected: prev[itemId]?.selected ?? true,
        quantity: clampReturnQuantity(quantity, max),
      },
    }));
  }

  async function submit() {
    if (!canSubmitInFlightAction(mutation.isPending)) return;

    const items = returnableItems
      .filter((item) => selections[item.id]?.selected)
      .map((item) => ({
        orderItemId: item.id,
        quantity: clampReturnQuantity(
          selections[item.id]?.quantity ?? item.quantity,
          item.quantity,
        ),
      }));

    if (items.length === 0) {
      setError('Select at least one item to return.');
      return;
    }
    if (!isSupportedReturnReason(reason)) {
      setError('Choose a return reason.');
      return;
    }

    setError(null);
    try {
      const created = await mutation.mutateAsync({
        reason: reason.trim(),
        description: description.trim() || null,
        customerNotes: customerNotes.trim() || null,
        items,
      });
      router.replace(buildReturnDetailHref(created.id) as never);
    } catch (err) {
      setError(getReturnErrorMessage(err));
    }
  }

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.eyebrow}>Returns</Text>
      <Text style={styles.heading}>Request a return</Text>
      <Text style={styles.note}>
        Select items from {order.orderNumber ?? order.id} and tell us why you are
        returning them.
      </Text>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Text style={styles.section}>Items</Text>
      {returnableItems.length === 0 ? (
        <Text style={styles.note}>
          Order line items are unavailable for return selection. Please contact support.
        </Text>
      ) : (
        returnableItems.map((item) => {
          const sel = selections[item.id];
          return (
            <View key={item.id} style={styles.itemRow}>
              <Pressable
                accessibilityRole="checkbox"
                accessibilityState={{ checked: Boolean(sel?.selected) }}
                onPress={() => toggleItem(item.id, item.quantity)}
                style={styles.itemToggle}
              >
                <Text style={styles.itemName}>{item.productName}</Text>
                <Text style={styles.note}>Ordered qty {item.quantity}</Text>
                <Text style={styles.caption}>
                  {sel?.selected ? 'Selected' : 'Tap to select'}
                </Text>
              </Pressable>
              {sel?.selected ? (
                <View style={styles.qtyRow}>
                  <Pressable
                    accessibilityRole="button"
                    onPress={() =>
                      setQty(item.id, (sel.quantity ?? 1) - 1, item.quantity)
                    }
                    style={styles.qtyButton}
                  >
                    <Text style={styles.optionLabel}>−</Text>
                  </Pressable>
                  <Text style={styles.qtyValue}>{sel.quantity}</Text>
                  <Pressable
                    accessibilityRole="button"
                    onPress={() =>
                      setQty(item.id, (sel.quantity ?? 1) + 1, item.quantity)
                    }
                    style={styles.qtyButton}
                  >
                    <Text style={styles.optionLabel}>+</Text>
                  </Pressable>
                </View>
              ) : null}
            </View>
          );
        })
      )}

      <Text style={styles.section}>Reason</Text>
      {RETURN_REASON_OPTIONS.map((option) => (
        <Pressable
          key={option}
          accessibilityRole="radio"
          accessibilityState={{ selected: reason === option }}
          onPress={() => setReason(option)}
          style={[styles.option, reason === option ? styles.optionSelected : null]}
        >
          <Text style={styles.optionLabel}>{option}</Text>
        </Pressable>
      ))}

      <TextInput
        value={description}
        onChangeText={setDescription}
        placeholder="Description (optional)"
        placeholderTextColor={colors.textSecondary}
        multiline
        style={styles.input}
      />
      <TextInput
        value={customerNotes}
        onChangeText={setCustomerNotes}
        placeholder="Notes for us (optional)"
        placeholderTextColor={colors.textSecondary}
        multiline
        style={styles.input}
      />

      <PrimaryButton
        label={mutation.isPending ? 'Submitting…' : 'Submit return request'}
        loading={mutation.isPending}
        disabled={mutation.isPending || returnableItems.length === 0}
        onPress={() => void submit()}
        style={styles.submit}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  fill: { flex: 1, backgroundColor: colors.background },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  heading: { ...typography.heading, marginTop: spacing.xs },
  note: { ...typography.caption, marginTop: spacing.xs },
  caption: { ...typography.caption, marginTop: spacing.xxs },
  section: {
    marginTop: spacing.xl,
    marginBottom: spacing.sm,
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
  },
  itemRow: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    marginBottom: spacing.sm,
    backgroundColor: colors.surface,
  },
  itemToggle: { flex: 1 },
  itemName: { ...typography.bodyStrong, color: colors.text },
  qtyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginTop: spacing.sm,
  },
  qtyButton: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
  },
  qtyValue: { ...typography.bodyStrong, color: colors.text },
  option: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    marginBottom: spacing.sm,
    backgroundColor: colors.surface,
  },
  optionSelected: { borderColor: colors.primary },
  optionLabel: { ...typography.bodyStrong, color: colors.text },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    marginTop: spacing.sm,
    minHeight: 72,
    textAlignVertical: 'top',
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.surface,
  },
  submit: { marginTop: spacing.xl },
  error: {
    marginTop: spacing.md,
    ...typography.bodyStrong,
    color: colors.error,
  },
});
