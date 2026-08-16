import { fetchCurrentAdmin, loginAdmin, logoutAdmin } from '@/src/features/auth/api/adminAuthApi';
import {
  deactivateAdminPushBestEffort,
  getOrCreateInstallationId,
  resetPushRegistrationState,
} from '@/src/features/notifications';
import { secureTokenStorage } from '@/src/core/storage';

import { useAdminAuthStore } from './adminAuthStore';

export async function bootstrapSession(): Promise<void> {
  const store = useAdminAuthStore.getState();
  store.setBootstrapping();

  const token = await secureTokenStorage.readToken();
  if (!token) {
    store.setUnauthenticated();
    return;
  }

  try {
    const admin = await fetchCurrentAdmin();
    store.setAuthenticated(admin);
  } catch {
    await secureTokenStorage.clearToken();
    store.setUnauthenticated();
  }
}

export async function login(email: string, password: string): Promise<void> {
  const result = await loginAdmin({ email, password });
  await secureTokenStorage.saveToken(result.token);
  useAdminAuthStore.getState().setAuthenticated(result.admin);
}

export async function logout(): Promise<void> {
  let installationId: string | undefined;
  try {
    installationId = await getOrCreateInstallationId();
  } catch {
    installationId = undefined;
  }

  try {
    await logoutAdmin(installationId ? { installation_id: installationId } : undefined);
  } catch {
    // Best-effort server logout; always clear local session.
  }

  await deactivateAdminPushBestEffort();
  resetPushRegistrationState();

  await secureTokenStorage.clearToken();
  useAdminAuthStore.getState().setUnauthenticated();
}
