import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';
import { secureTokenStorage } from '@/src/core/storage';
import { userSchema, type User } from '@/src/shared/types/user';
import { useAuthStore } from './authStore';
import { clearSessionOnAuthFailure } from './clearSession';

export type BootstrapAuthResult =
  | { status: 'authenticated'; user: User }
  | { status: 'unauthenticated'; reason: 'no_token' | 'unauthenticated' | 'invalid_user' | 'error' };

/**
 * Startup auth decision (Contract v1 — no refresh token):
 * 1. Read SecureStore token
 * 2. If present, GET /me
 * 3. Valid → authenticated; unauthenticated → clearSessionOnAuthFailure (preserves payment proof)
 */
export async function bootstrapAuth(): Promise<BootstrapAuthResult> {
  const auth = useAuthStore.getState();
  auth.setBootstrapping();

  const token = await secureTokenStorage.readToken();
  if (!token) {
    auth.setUnauthenticated();
    return { status: 'unauthenticated', reason: 'no_token' };
  }

  try {
    const response = await apiClient.get<unknown>('/me', undefined, token);
    const parsed = userSchema.safeParse(response.data);
    if (!parsed.success) {
      await clearSessionOnAuthFailure();
      return { status: 'unauthenticated', reason: 'invalid_user' };
    }

    auth.setAuthenticated(parsed.data);
    return { status: 'authenticated', user: parsed.data };
  } catch (error) {
    if (error instanceof ApiError && error.isUnauthenticated) {
      await clearSessionOnAuthFailure();
      return { status: 'unauthenticated', reason: 'unauthenticated' };
    }

    // Network / transient: keep token; route as unauthenticated until retry.
    auth.setUnauthenticated();
    return { status: 'unauthenticated', reason: 'error' };
  }
}

export {
  clearSession,
  clearSessionOnAuthFailure,
  clearSessionOnLogout,
  type ClearSessionOptions,
} from './clearSession';
