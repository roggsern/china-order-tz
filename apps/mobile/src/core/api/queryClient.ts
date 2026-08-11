import { QueryCache, QueryClient } from '@tanstack/react-query';
import {
  AUTHENTICATED_QUERY_META,
  handleAuthenticatedRequestAuthFailure,
  isUnauthenticatedApiError,
} from '@/src/core/auth/handleAuthenticatedAuthFailure';

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
        retry: 1,
        staleTime: 30_000,
        refetchOnWindowFocus: false,
      },
      mutations: {
        retry: 0,
      },
    },
  });
}

export { AUTHENTICATED_QUERY_META };
