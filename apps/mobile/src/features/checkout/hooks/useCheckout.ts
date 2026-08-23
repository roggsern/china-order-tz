import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AUTHENTICATED_QUERY_META,
  clearSessionOnAuthFailure,
  useAuthStore,
} from '@/src/core/auth';
import { cartQueryKey } from '@/src/features/cart';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import {
  applyCheckoutShippingChoice,
  cancelCheckoutSessionSafely,
  prepareCheckout,
  refreshCheckoutSession,
  startCheckoutSession,
  updateDeliveryAddress,
} from '../api/checkoutApi';
import type { ApplyShippingChoiceInput, DeliveryAddressInput } from '../models/types';
import { isCheckoutUnauthenticatedError } from '../utils/checkoutErrorMessage';
import {
  checkoutPrepareQueryKey,
  checkoutSessionQueryKey,
  invalidateAfterCheckoutCancel,
} from '../utils/checkoutQueryKeys';

export {
  checkoutPrepareQueryKey,
  checkoutSessionQueryKey,
  invalidateAfterCheckoutCancel,
} from '../utils/checkoutQueryKeys';

function useCheckoutAuthGuard() {
  return (error: unknown) => {
    if (isCheckoutUnauthenticatedError(error)) {
      void clearSessionOnAuthFailure().then(() => {
        router.push(buildLoginHref('/(app)/checkout'));
      });
      return true;
    }
    return false;
  };
}

export function useCheckoutPrepare(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: checkoutPrepareQueryKey(),
    queryFn: prepareCheckout,
    enabled: enabled && authStatus === 'authenticated',
    retry: false,
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useStartCheckoutSessionMutation() {
  const queryClient = useQueryClient();
  const handleAuth = useCheckoutAuthGuard();

  return useMutation({
    mutationFn: () => startCheckoutSession(),
    onSuccess: (session) => {
      queryClient.setQueryData(checkoutSessionQueryKey(session.id), session);
      void queryClient.invalidateQueries({ queryKey: cartQueryKey() });
    },
    onError: handleAuth,
  });
}

export function useRefreshCheckoutSessionMutation() {
  const queryClient = useQueryClient();
  const handleAuth = useCheckoutAuthGuard();

  return useMutation({
    mutationFn: (sessionId: string) => refreshCheckoutSession(sessionId),
    onSuccess: (session) => {
      queryClient.setQueryData(checkoutSessionQueryKey(session.id), session);
      void queryClient.invalidateQueries({ queryKey: cartQueryKey() });
      void queryClient.invalidateQueries({ queryKey: checkoutPrepareQueryKey() });
    },
    onError: handleAuth,
  });
}

export function useApplyShippingChoiceMutation() {
  const queryClient = useQueryClient();
  const handleAuth = useCheckoutAuthGuard();

  return useMutation({
    mutationFn: (input: { sessionId: string } & ApplyShippingChoiceInput) =>
      applyCheckoutShippingChoice(input.sessionId, input),
    onSuccess: (session) => {
      queryClient.setQueryData(checkoutSessionQueryKey(session.id), session);
      void queryClient.invalidateQueries({ queryKey: cartQueryKey() });
      void queryClient.invalidateQueries({ queryKey: checkoutPrepareQueryKey() });
    },
    onError: handleAuth,
  });
}

export function useCancelCheckoutSessionMutation() {
  const queryClient = useQueryClient();
  const handleAuth = useCheckoutAuthGuard();

  return useMutation({
    mutationFn: (sessionId: string) => cancelCheckoutSessionSafely(sessionId),
    onSuccess: (_result, sessionId) => {
      void invalidateAfterCheckoutCancel(queryClient, sessionId);
    },
    onError: handleAuth,
  });
}

export function useUpdateDeliveryAddressMutation() {
  const queryClient = useQueryClient();
  const handleAuth = useCheckoutAuthGuard();

  return useMutation({
    mutationFn: (input: DeliveryAddressInput) => updateDeliveryAddress(input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: checkoutPrepareQueryKey() });
    },
    onError: handleAuth,
  });
}
