import { QueryCache, QueryClient } from '@tanstack/react-query';
import {
  AUTHENTICATED_QUERY_META,
  handleAuthenticatedRequestAuthFailure,
  isUnauthenticatedApiError,
} from '@/src/core/auth/handleAuthenticatedAuthFailure';
import { shouldRetryMutation, shouldRetryTransientRead } from './queryRetryPolicy';

export function createAppQueryClient(): QueryClient {
  return new QueryClient({
    queryCache: new QueryCache({
      onError: (error, query) => {
        const requiresAuth = Boolean(
          (query.meta as { requiresAuth?: boolean } | undefined)?.requiresAuth,
        );
        if (!requiresAuth) return;
        if (!isUnauthenticatedApiError(error)) return;
        void handleAuthenticatedRequestAuthFailure();
      },
    }),
    defaultOptions: {
      queries: {
        retry: (failureCount, error) => shouldRetryTransientRead(error, failureCount),
        retryDelay: 750,
        staleTime: 30_000,
        refetchOnWindowFocus: false,
      },
      mutations: {
        retry: () => shouldRetryMutation(),
      },
    },
  });
}

export { AUTHENTICATED_QUERY_META };
