import { ApiError } from '@/src/core/errors';
import { clearSessionOnAuthFailure } from '@/src/core/auth/clearSession';

let authFailureInFlight: Promise<void> | null = null;

/**
 * Shared authenticated-query/mutation 401 handling.
 * Preserves payment + checkout recovery contexts.
 * Dedupes concurrent 401s to avoid redirect storms.
 */
export function isUnauthenticatedApiError(error: unknown): boolean {
  return error instanceof ApiError && error.isUnauthenticated;
}

export async function handleAuthenticatedRequestAuthFailure(): Promise<void> {
  if (!authFailureInFlight) {
    authFailureInFlight = clearSessionOnAuthFailure().finally(() => {
      authFailureInFlight = null;
    });
  }
  await authFailureInFlight;
}

/** QueryClient meta flag for private commerce queries. */
export const AUTHENTICATED_QUERY_META = { requiresAuth: true } as const;
