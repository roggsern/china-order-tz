import { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { CartLineItemCard } from '../components/CartLineItemCard';
import { CartTotals } from '../components/CartTotals';
import { ProceedToCheckoutButton } from '../components/ProceedToCheckoutButton';
import {
  useCart,
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
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyItemId, setBusyItemId] = useState<string | null>(null);

  if (authStatus !== 'authenticated') {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Cart</Text>
        <Text style={styles.body}>Please sign in to view your cart.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => router.push(buildLoginHref('/(app)/(tabs)/cart'))}
        >
          <Text style={styles.primaryButtonText}>Sign in</Text>
        </Pressable>
      </View>
    );
  }

  if (cartQuery.isLoading && !cartQuery.data) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#0a7ea4" />
        <Text style={styles.muted}>Loading cart…</Text>
      </View>
    );
  }

  if (cartQuery.isError && !cartQuery.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Cart unavailable</Text>
        <Text style={styles.body}>{getCartErrorMessage(cartQuery.error)}</Text>
        <Pressable style={styles.primaryButton} onPress={() => void cartQuery.refetch()}>
          <Text style={styles.primaryButtonText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  const cart = cartQuery.data;
  const items = cart?.items ?? [];
  const isEmpty = !cart || cart.isEmpty || items.length === 0;
  const mutating = updateMutation.isPending || removeMutation.isPending;

  async function changeQuantity(itemId: string, quantity: number) {
    setActionError(null);
    setBusyItemId(itemId);
    try {
      await updateMutation.mutateAsync({ itemId, quantity });
    } catch (error) {
      setActionError(getCartErrorMessage(error));
    } finally {
      setBusyItemId(null);
    }
  }

  async function removeItem(itemId: string) {
    setActionError(null);
    setBusyItemId(itemId);
    try {
      await removeMutation.mutateAsync(itemId);
    } catch (error) {
      setActionError(getCartErrorMessage(error));
    } finally {
      setBusyItemId(null);
    }
  }

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={cartQuery.isRefetching}
          onRefresh={() => void cartQuery.refetch()}
        />
      }
    >
      <Text style={styles.subheading}>
        Prices and availability come from the server. Journeys cannot be mixed in one cart.
      </Text>

      {actionError ? <Text style={styles.error}>{actionError}</Text> : null}

      {isEmpty ? (
        <View style={styles.empty}>
          <Text style={styles.emptyTitle}>Your cart is empty</Text>
          <Text style={styles.body}>Add products from the catalog or search.</Text>
        </View>
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
          <ProceedToCheckoutButton disabled={isEmpty || mutating} />
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    padding: 16,
    paddingBottom: 40,
  },
  heading: {
    fontSize: 22,
    fontWeight: '700',
    color: '#111',
  },
  subheading: {
    marginTop: 6,
    marginBottom: 12,
    fontSize: 13,
    color: '#666',
    lineHeight: 18,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#222',
  },
  body: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  muted: {
    fontSize: 14,
    color: '#666',
  },
  empty: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 8,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#222',
  },
  error: {
    marginBottom: 12,
    color: '#b00020',
    fontSize: 14,
  },
  primaryButton: {
    marginTop: 8,
    backgroundColor: '#0a7ea4',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
});
