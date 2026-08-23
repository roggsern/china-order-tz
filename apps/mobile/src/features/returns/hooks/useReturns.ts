import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AUTHENTICATED_QUERY_META,
  clearSessionOnAuthFailure,
  useAuthStore,
} from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { invalidateOrdersQueries } from '@/src/features/orders/hooks/useOrders';
import {
  createCustomerReturn,
  fetchCustomerReturn,
  fetchCustomerReturns,
} from '../api/returnsApi';
import type { CreateReturnInput } from '../models/types';
import { isReturnUnauthenticatedError } from '../utils/returnErrorMessage';
import {
  returnDetailQueryKey,
  returnsListQueryKey,
  returnsRootQueryKey,
} from '../utils/returnQueryKeys';
import { buildReturnsListHref } from '../utils/returnRoutes';

function useReturnsAuthGuard(returnTo: string = buildReturnsListHref()) {
  return (error: unknown) => {
    if (isReturnUnauthenticatedError(error)) {
      void clearSessionOnAuthFailure().then(() => {
        router.push(buildLoginHref(returnTo));
      });
      return true;
    }
    return false;
  };
}

export function useCustomerReturnsList(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: returnsListQueryKey(),
    queryFn: fetchCustomerReturns,
    enabled: enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useCustomerReturnDetail(returnId: string | null, enabled = true) {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: returnDetailQueryKey(returnId ?? ''),
    queryFn: () => fetchCustomerReturn(returnId!),
    enabled:
      Boolean(returnId) && enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useCreateReturnMutation(orderId: string) {
  const queryClient = useQueryClient();
  const handleAuth = useReturnsAuthGuard(
    `/(app)/orders/${encodeURIComponent(orderId)}/return`,
  );

  return useMutation({
    mutationFn: (input: Omit<CreateReturnInput, 'orderId'>) =>
      createCustomerReturn({ orderId, ...input }),
    onSuccess: (created) => {
      queryClient.setQueryData(returnDetailQueryKey(created.id), created);
      void queryClient.invalidateQueries({ queryKey: returnsRootQueryKey() });
      void invalidateOrdersQueries(queryClient, orderId);
    },
    onError: handleAuth,
  });
}
