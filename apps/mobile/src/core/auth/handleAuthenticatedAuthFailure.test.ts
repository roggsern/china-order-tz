import { ApiError } from '@/src/core/errors';
import { QueryCache, QueryClient } from '@tanstack/react-query';
import {
  AUTHENTICATED_QUERY_META,
  handleAuthenticatedRequestAuthFailure,
  isUnauthenticatedApiError,
} from '@/src/core/auth/handleAuthenticatedAuthFailure';
import { clearSessionOnAuthFailure } from '@/src/core/auth/clearSession';

jest.mock('@/src/core/auth/clearSession', () => ({
  clearSessionOnAuthFailure: jest.fn(async () => undefined),
}));

const mockClearOnAuthFailure = clearSessionOnAuthFailure as jest.Mock;

describe('authenticated query 401 strategy', () => {
  beforeEach(() => {
    mockClearOnAuthFailure.mockClear();
  });

  it('recognizes unauthenticated API errors', () => {
    expect(
      isUnauthenticatedApiError(
        new ApiError({ message: 'x', status: 401, code: 'unauthenticated' }),
      ),
    ).toBe(true);
    expect(
      isUnauthenticatedApiError(
        new ApiError({ message: 'x', status: 500, code: 'server_error' }),
      ),
    ).toBe(false);
  });

  it('authenticated query 401 clears invalid auth via shared handler', async () => {
    const onError = jest.fn((error: unknown, query: { meta?: unknown }) => {
      const requiresAuth = Boolean(
        (query.meta as { requiresAuth?: boolean } | undefined)?.requiresAuth,
      );
      if (!requiresAuth) return;
      if (!isUnauthenticatedApiError(error)) return;
      void handleAuthenticatedRequestAuthFailure();
    });

    const client = new QueryClient({
      queryCache: new QueryCache({ onError }),
    });

    await client.fetchQuery({
      queryKey: ['cart', 'current'],
      queryFn: async () => {
        throw new ApiError({
          message: 'Unauthenticated',
          status: 401,
          code: 'unauthenticated',
        });
      },
      meta: AUTHENTICATED_QUERY_META,
      retry: false,
    }).catch(() => undefined);

    await Promise.resolve();
    expect(mockClearOnAuthFailure).toHaveBeenCalled();
  });

  it('public queries are unaffected by auth meta handler', async () => {
    const client = new QueryClient({
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
    });

    await client.fetchQuery({
      queryKey: ['storefront', 'homepage', 'CHINA_IMPORT'],
      queryFn: async () => {
        throw new ApiError({
          message: 'Unauthenticated',
          status: 401,
          code: 'unauthenticated',
        });
      },
      retry: false,
    }).catch(() => undefined);

    await Promise.resolve();
    expect(mockClearOnAuthFailure).not.toHaveBeenCalled();
  });
});
