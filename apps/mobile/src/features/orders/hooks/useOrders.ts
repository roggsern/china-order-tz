import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
} from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AUTHENTICATED_QUERY_META,
  clearSessionOnAuthFailure,
  useAuthStore,
} from '@/src/core/auth';
import { cartQueryKey } from '@/src/features/cart/hooks/useCart';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import {
  cancelOrder,
  fetchOrderDetail,
  fetchOrderTracking,
  fetchOrders,
  selectReceivingMethod,
} from '../api/ordersApi';
import type { CancelOrderInput, OrdersListFilter, SelectReceivingMethodInput } from '../models/types';
import { isOrderUnauthenticatedError } from '../utils/orderErrorMessage';
import { buildOrdersListHref } from '../utils/orderRoutes';
import {
  orderDetailQueryKey,
  orderTrackingQueryKey,
  ordersListQueryKey,
  ordersRootQueryKey,
} from '../utils/ordersQueryKeys';

export {
  orderDetailQueryKey,
  orderTrackingQueryKey,
  ordersListQueryKey,
  ordersRootQueryKey,
} from '../utils/ordersQueryKeys';

function useOrdersAuthGuard(returnTo: string = buildOrdersListHref()) {
  return (error: unknown) => {
    if (isOrderUnauthenticatedError(error)) {
      void clearSessionOnAuthFailure().then(() => {
        router.push(buildLoginHref(returnTo));
      });
      return true;
    }
    return false;
  };
}

export async function invalidateOrdersQueries(
  queryClient: QueryClient,
  orderId?: string | null,
): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: ordersRootQueryKey() });
  if (orderId) {
    await queryClient.invalidateQueries({
      queryKey: orderDetailQueryKey(orderId),
    });
    await queryClient.invalidateQueries({
      queryKey: orderTrackingQueryKey(orderId),
    });
  }
}

/** Invalidate payment + cart + orders after server-confirmed payment. */
export async function invalidateAfterPaymentSuccess(
  queryClient: QueryClient,
  orderId?: string | null,
): Promise<void> {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: ['payments'] }),
    queryClient.invalidateQueries({ queryKey: cartQueryKey() }),
    invalidateOrdersQueries(queryClient, orderId),
  ]);
}

export function useOrdersList(options?: {
  filter?: OrdersListFilter;
  perPage?: number;
  enabled?: boolean;
}) {
  const authStatus = useAuthStore((s) => s.status);
  const filter = options?.filter ?? 'all';
  const perPage = options?.perPage ?? 10;
  const enabled =
    (options?.enabled ?? true) && authStatus === 'authenticated';

  return useInfiniteQuery({
    queryKey: ordersListQueryKey(filter),
    queryFn: ({ pageParam }) =>
      fetchOrders({
        filter,
        page: pageParam,
        perPage,
      }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) => lastPage.nextPage ?? undefined,
    enabled,
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useOrderDetail(orderId: string | null, enabled = true) {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: orderDetailQueryKey(orderId ?? ''),
    queryFn: () => fetchOrderDetail(orderId!),
    enabled:
      Boolean(orderId) && enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useOrderTracking(orderId: string | null, enabled = true) {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: orderTrackingQueryKey(orderId ?? ''),
    queryFn: () => fetchOrderTracking(orderId!),
    enabled:
      Boolean(orderId) && enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useCancelOrderMutation(orderId: string) {
  const queryClient = useQueryClient();
  const handleAuth = useOrdersAuthGuard(buildOrderDetailReturn(orderId));

  return useMutation({
    mutationFn: (input?: { reason?: string | null }) =>
      cancelOrder({
        orderId,
        reason: input?.reason,
      } satisfies CancelOrderInput),
    onSuccess: (detail) => {
      queryClient.setQueryData(orderDetailQueryKey(orderId), detail);
      void invalidateOrdersQueries(queryClient, orderId);
    },
    onError: handleAuth,
  });
}

export function useSelectReceivingMethodMutation(orderId: string) {
  const queryClient = useQueryClient();
  const handleAuth = useOrdersAuthGuard(buildOrderDetailReturn(orderId));

  return useMutation({
    mutationFn: (receivingMethod: SelectReceivingMethodInput['receivingMethod']) =>
      selectReceivingMethod({ orderId, receivingMethod }),
    onSuccess: () => {
      void invalidateOrdersQueries(queryClient, orderId);
    },
    onError: handleAuth,
  });
}

function buildOrderDetailReturn(orderId: string): string {
  return `/(app)/orders/${encodeURIComponent(orderId)}`;
}
