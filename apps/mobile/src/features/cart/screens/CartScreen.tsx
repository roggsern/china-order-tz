import { useRef, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { createExclusiveLock } from '@/src/core/async/exclusiveLock';
import { useAuthStore } from '@/src/core/auth';
import { cancelCheckoutSessionSafely } from '@/src/features/checkout/api/checkoutApi';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { shouldCancelCheckoutSession } from '@/src/features/checkout/utils/cancelCheckoutSession';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { TrustStrip } from '@/src/shared/ui/TrustStrip';
import { colors, spacing, typography } from '@/src/shared/theme';
import { CartLineItemCard } from '../components/CartLineItemCard';
import { CartTotals } from '../components/CartTotals';
import { ProceedToCheckoutButton } from '../components/ProceedToCheckoutButton';
import {
  useCart,
  useClearCartMutation,
  useRemoveCartItemMutation,
  useUpdateCartItemMutation,
} from '../hooks/useCart';
import { buildLoginHref } from '../utils/authReturn';
import { getCartErrorMessage } from '../utils/cartErrorMessage';

export function CartScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const cartQuery = useCart();
  const updateMutation = useUpdateCartItemMutation();
  const removeMutation = useRemoveCartItemMutation();
  const clearMutation = useClearCartMutation();
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyItemId, setBusyItemId] = useState<string | null>(null);
  const cartActionLockRef = useRef(createExclusiveLock());

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Your cart"
        message="Please sign in to view your cart."
        actionLabel="Sign in"
        onActionPress={() => router.push(buildLoginHref('/(app)/(tabs)/cart'))}
        style={styles.fill}
      />
    );
  }

  if (cartQuery.isLoading && !cartQuery.data) {
    return <ScreenLoadingState label="Loading cart…" />;
  }

  if (cartQuery.isError && !cartQuery.data) {
    return (
      <EmptyState
        title="Cart unavailable"
        message={getCartErrorMessage(cartQuery.error)}
        actionLabel="Retry"
        onActionPress={() => void cartQuery.refetch()}
        style={styles.fill}
      />
    );
  }

  const cart = cartQuery.data;
  const items = cart?.items ?? [];
  const isEmpty = !cart || cart.isEmpty || items.length === 0;
  const mutating =
    updateMutation.isPending || removeMutation.isPending || clearMutation.isPending;

  async function changeQuantity(itemId: string, quantity: number) {
    if (!cartActionLockRef.current.tryAcquire()) return;
    setActionError(null);
    setBusyItemId(itemId);
    try {
      await updateMutation.mutateAsync({ itemId, quantity });
    } catch (error) {
      setActionError(getCartErrorMessage(error));
    } finally {
      setBusyItemId(null);
      cartActionLockRef.current.release();
    }
  }

  async function removeItem(itemId: string) {
    if (!cartActionLockRef.current.tryAcquire()) return;
    setActionError(null);
    setBusyItemId(itemId);
    try {
      await removeMutation.mutateAsync(itemId);
    } catch (error) {
      setActionError(getCartErrorMessage(error));
    } finally {
      setBusyItemId(null);
      cartActionLockRef.current.release();
    }
  }

  function confirmClearCart() {
    Alert.alert(
      'Clear cart?',
      'This removes every item from your server cart.',
      [
        { text: 'Keep items', style: 'cancel' },
        {
          text: 'Clear cart',
          style: 'destructive',
          onPress: () => void clearAllItems(),
        },
      ],
    );
  }

  async function clearAllItems() {
    if (!cartActionLockRef.current.tryAcquire()) return;
    setActionError(null);
    try {
      await clearMutation.mutateAsync();
      const stored = await pendingCheckoutContextStorage.read();
      if (
        stored?.checkoutSessionId &&
        shouldCancelCheckoutSession({
          sessionId: stored.checkoutSessionId,
          orderId: stored.orderId,
        })
      ) {
        try {
          await cancelCheckoutSessionSafely(stored.checkoutSessionId);
        } catch {
          // Cart already cleared; leftover session cancel is best-effort.
        }
        await pendingCheckoutContextStorage.clear();
      }
    } catch (error) {
      setActionError(getCartErrorMessage(error));
    } finally {
      cartActionLockRef.current.release();
    }
  }

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={cartQuery.isRefetching}
            onRefresh={() => void cartQuery.refetch()}
            tintColor={colors.primary}
            colors={[colors.primary]}
          />
        }
      >
        <View style={styles.header}>
          <Text style={styles.eyebrow}>Bag</Text>
          <Text style={styles.heading}>Your cart</Text>
          <Text style={styles.subheading}>
            Prices and availability come from the server. Journeys cannot be mixed
            in one cart.
          </Text>
        </View>

        {actionError ? <Text style={styles.error}>{actionError}</Text> : null}

        {isEmpty ? (
          <EmptyState
            title="Your cart is empty"
            message="Add products from Shop or Search to continue."
            actionLabel="Shop products"
            onActionPress={() => router.push('/(app)/(tabs)/browse')}
            style={styles.empty}
          />
        ) : (
          <>
            {items.map((item) => (
              <CartLineItemCard
                key={item.id}
                item={item}
                busy={busyItemId === item.id && mutating}
                onIncrease={() => void changeQuantity(item.id, item.quantity + 1)}
                onDecrease={() => void changeQuantity(item.id, item.quantity - 1)}
                onRemove={() => void removeItem(item.id)}
              />
            ))}

            {cart ? <CartTotals cart={cart} /> : null}
            <SecondaryButton
              label="Clear cart"
              disabled={mutating}
              loading={clearMutation.isPending}
              onPress={confirmClearCart}
              style={styles.clearButton}
            />
            <ProceedToCheckoutButton disabled={isEmpty || mutating} />
            <TrustStrip
              title="Before you checkout"
              items={[
                {
                  id: 'server-totals',
                  title: 'Server-confirmed totals',
                  description:
                    'Line prices and cart totals are never recalculated on device.',
                },
                {
                  id: 'journey',
                  title: 'One shopping journey',
                  description:
                    'China import and Tanzania store items stay in separate carts.',
                },
              ]}
            />
          </>
        )}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: {
    backgroundColor: colors.background,
  },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  fill: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    marginBottom: spacing.sm,
  },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: {
    ...typography.heading,
  },
  subheading: {
    ...typography.caption,
    marginTop: spacing.xs,
    marginBottom: spacing.lg,
  },
  empty: {
    paddingVertical: spacing.xxxl,
  },
  error: {
    marginBottom: spacing.md,
    ...typography.body,
    color: colors.error,
  },
  clearButton: {
    marginTop: spacing.md,
    alignSelf: 'stretch',
  },
});
