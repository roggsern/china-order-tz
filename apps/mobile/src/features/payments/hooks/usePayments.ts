import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AUTHENTICATED_QUERY_META,
  clearSessionOnAuthFailure,
  useAuthStore,
} from '@/src/core/auth';
import { cartQueryKey } from '@/src/features/cart';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { checkoutPrepareQueryKey } from '@/src/features/checkout';
import { ordersRootQueryKey } from '@/src/features/orders/utils/ordersQueryKeys';
import {
  createOrderFromCheckoutSession,
  fetchPaymentMethods,
  fetchPaymentTransaction,
  reconcileNmbBrowserReturn,
  refreshPaymentTransaction,
  retryNmbCheckoutSession,
  startPayment,
} from '../api/paymentsApi';
import type { ReconcileNmbReturnInput } from '../models/types';
import { buildPaymentHref } from '../utils/paymentRoutes';
import { isPaymentUnauthenticatedError } from '../utils/paymentErrorMessage';

export function paymentMethodsQueryKey() {
  return ['payments', 'methods'] as const;
}

export function paymentTransactionQueryKey(transactionId: string | null) {
  return ['payments', 'transaction', transactionId] as const;
}

function usePaymentAuthGuard(returnHref: string = buildPaymentHref()) {
  return (error: unknown) => {
    if (isPaymentUnauthenticatedError(error)) {
      void clearSessionOnAuthFailure().then(() => {
        router.push(buildLoginHref(returnHref));
      });
      return true;
    }
    return false;
  };
}

export function usePaymentMethods(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: paymentMethodsQueryKey(),
    queryFn: fetchPaymentMethods,
    enabled: enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function usePaymentTransaction(transactionId: string | null, enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: paymentTransactionQueryKey(transactionId),
    queryFn: () => fetchPaymentTransaction(transactionId!),
    enabled: Boolean(transactionId) && enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useCreateOrderFromCheckoutMutation() {
  const queryClient = useQueryClient();
  const handleAuth = usePaymentAuthGuard();

  return useMutation({
    mutationFn: (checkoutSessionId: string) =>
      createOrderFromCheckoutSession(checkoutSessionId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: cartQueryKey() });
      void queryClient.invalidateQueries({ queryKey: checkoutPrepareQueryKey() });
      void queryClient.invalidateQueries({ queryKey: ordersRootQueryKey() });
    },
    onError: handleAuth,
  });
}

export function useStartPaymentMutation() {
  const queryClient = useQueryClient();
  const handleAuth = usePaymentAuthGuard();

  return useMutation({
    mutationFn: (input: {
      orderId: string;
      provider?: string | null;
      phoneNumber?: string | null;
    }) => startPayment(input.orderId, input),
    onSuccess: (transaction) => {
      queryClient.setQueryData(
        paymentTransactionQueryKey(transaction.id),
        transaction,
      );
    },
    onError: handleAuth,
  });
}

export function useRefreshPaymentMutation() {
  const queryClient = useQueryClient();
  const handleAuth = usePaymentAuthGuard();

  return useMutation({
    mutationFn: (transactionId: string) => refreshPaymentTransaction(transactionId),
    onSuccess: (transaction) => {
      queryClient.setQueryData(
        paymentTransactionQueryKey(transaction.id),
        transaction,
      );
    },
    onError: handleAuth,
  });
}

export function useRetryNmbCheckoutMutation() {
  const queryClient = useQueryClient();
  const handleAuth = usePaymentAuthGuard();

  return useMutation({
    mutationFn: (transactionId: string) => retryNmbCheckoutSession(transactionId),
    onSuccess: (transaction) => {
      queryClient.setQueryData(
        paymentTransactionQueryKey(transaction.id),
        transaction,
      );
    },
    onError: handleAuth,
  });
}

export function useReconcileNmbReturnMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: ReconcileNmbReturnInput) => reconcileNmbBrowserReturn(input),
    onSuccess: (transaction) => {
      queryClient.setQueryData(
        paymentTransactionQueryKey(transaction.id),
        transaction,
      );
    },
  });
}
