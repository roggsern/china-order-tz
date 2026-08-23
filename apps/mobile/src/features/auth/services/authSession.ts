import { apiClient } from '@/src/core/api';
import { clearSessionOnLogout } from '@/src/core/auth/clearSession';
import { useAuthStore } from '@/src/core/auth/authStore';
import { ApiError } from '@/src/core/errors';
import { secureTokenStorage } from '@/src/core/storage';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { getOrCreateInstallationId } from '@/src/features/devices';
import {
  deactivatePushOnLogout,
  getLastRegisteredPushToken,
  resetPushRegistrationState,
} from '@/src/features/notifications';
import { pendingPaymentContextStorage } from '@/src/features/payments/storage/pendingPaymentContextStorage';
import type { User } from '@/src/shared/types/user';
import {
  authSessionResponseSchema,
  type LoginRequest,
  type RegisterRequest,
} from '../api/schemas';
async function establishSession(raw: unknown): Promise<User> {
  const parsed = authSessionResponseSchema.safeParse(raw);
  if (!parsed.success) {
    throw new ApiError({
      message: 'Unexpected authentication response',
      status: 500,
      code: 'server_error',
      raw: raw && typeof raw === 'object' ? (raw as never) : null,
    });
  }

  await secureTokenStorage.saveToken(parsed.data.token);
  useAuthStore.getState().setAuthenticated(parsed.data.data);
  const userId = parsed.data.data.id;
  // Isolate recovery: same user keeps; different/unbound clears.
  await pendingPaymentContextStorage.bindToAuthenticatedUser(userId);
  await pendingCheckoutContextStorage.bindToAuthenticatedUser(userId);
  return parsed.data.data;
}

/** POST /login — persists Sanctum token in SecureStore, updates Zustand user/session. */
export async function loginWithPassword(input: LoginRequest): Promise<User> {
  const response = await apiClient.post<unknown>(
    '/login',
    {
      email: input.email.trim(),
      password: input.password,
    },
    null,
  );
  return establishSession(response);
}

/** POST /register — same session establishment as login. */
export async function registerAccount(input: RegisterRequest): Promise<User> {
  const body: Record<string, string> = {
    name: input.name.trim(),
    email: input.email.trim(),
    password: input.password,
    password_confirmation: input.password_confirmation,
    registration_source: 'self_registration',
  };

  const phone = input.phone?.trim();
  if (phone) {
    body.phone = phone;
  }

  const response = await apiClient.post<unknown>('/register', body, null);
  return establishSession(response);
}

/**
 * POST /logout then destructive clearSessionOnLogout
 * (token + auth + user caches + journey + payment/checkout recovery).
 * Sends installation_id when available so only this device's push ownership detaches.
 */
export async function logout(): Promise<void> {
  const pushToken = getLastRegisteredPushToken();
  let installationId: string | undefined;
  try {
    installationId = await getOrCreateInstallationId();
  } catch {
    installationId = undefined;
  }

  try {
    await deactivatePushOnLogout();
  } catch {
    // Dedicated deactivate is best-effort; /logout still detaches when possible.
  }

  try {
    await apiClient.post('/logout', {
      ...(installationId ? { installation_id: installationId } : {}),
      ...(pushToken ? { push_token: pushToken } : {}),
    });
  } catch {
    // Still clear local session (already unauthenticated / offline).
  } finally {
    // Clear in-memory push registration cache; installation_id stays for next account.
    resetPushRegistrationState();
    await clearSessionOnLogout();
  }
}
