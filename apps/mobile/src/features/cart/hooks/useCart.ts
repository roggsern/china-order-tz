import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AUTHENTICATED_QUERY_META,
  clearSessionOnAuthFailure,
  useAuthStore,
} from '@/src/core/auth';
import { buildLoginHref } from '../utils/authReturn';
import {
  addToCart,
  fetchCart,
  removeCartItem,
  updateCartItemQuantity,
} from '../api/cartApi';
import type { AddToCartInput, Cart } from '../models/types';
import { isCartUnauthenticatedError } from '../utils/cartErrorMessage';

export function cartQueryKey() {
  return ['cart', 'current'] as const;
}

function useSyncCartCache() {
  const queryClient = useQueryClient();

  return {
    applyCart: (cart: Cart) => {
      queryClient.setQueryData(cartQueryKey(), cart);
      void queryClient.invalidateQueries({ queryKey: cartQueryKey() });
    },
    handleAuthError: (error: unknown) => {
      if (isCartUnauthenticatedError(error)) {
        void clearSessionOnAuthFailure().then(() => {
          router.push(buildLoginHref('/(app)/(tabs)/cart'));
        });
        return true;
      }
      return false;
    },
  };
}

/**
 * Active cart for the authenticated customer.
 * Disabled when unauthenticated — no guest cart.
 */
export function useCart() {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: cartQueryKey(),
    queryFn: fetchCart,
    enabled: authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

/** @deprecated Prefer useCart() */
export function useCartQuery(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: cartQueryKey(),
    queryFn: fetchCart,
    enabled: enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useAddToCartMutation() {
  const { applyCart, handleAuthError } = useSyncCartCache();

  return useMutation({
    mutationFn: (input: AddToCartInput) => addToCart(input),
    onSuccess: (cart) => {
      applyCart(cart);
    },
    onError: (error) => {
      handleAuthError(error);
    },
  });
}

export function useUpdateCartItemMutation() {
  const { applyCart, handleAuthError } = useSyncCartCache();

  return useMutation({
    mutationFn: (input: { itemId: string; quantity: number }) =>
      updateCartItemQuantity(input.itemId, input.quantity),
    onSuccess: (cart) => {
      applyCart(cart);
    },
    onError: (error) => {
      handleAuthError(error);
    },
  });
}

export function useRemoveCartItemMutation() {
  const { applyCart, handleAuthError } = useSyncCartCache();

  return useMutation({
    mutationFn: (itemId: string) => removeCartItem(itemId),
    onSuccess: (cart) => {
      applyCart(cart);
    },
    onError: (error) => {
      handleAuthError(error);
    },
  });
}
