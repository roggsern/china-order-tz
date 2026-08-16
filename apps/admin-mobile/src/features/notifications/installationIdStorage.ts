import * as SecureStore from 'expo-secure-store';

export const ADMIN_INSTALLATION_ID_KEY = 'cotz.admin.installation_id';

function createUuidV4(): string {
  if (
    typeof globalThis.crypto !== 'undefined' &&
    typeof globalThis.crypto.randomUUID === 'function'
  ) {
    return globalThis.crypto.randomUUID();
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;
    const value = char === 'x' ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}

/**
 * Stable per-app-install identity for admin push-token registration.
 * Not an auth credential. Persisted in SecureStore.
 */
export async function getOrCreateInstallationId(): Promise<string> {
  const existing = await SecureStore.getItemAsync(ADMIN_INSTALLATION_ID_KEY);
  if (existing && existing.trim() !== '') {
    return existing.trim().toLowerCase();
  }

  const created = createUuidV4().toLowerCase();
  await SecureStore.setItemAsync(ADMIN_INSTALLATION_ID_KEY, created);
  return created;
}

/** Test / teardown helper — does not clear auth tokens. */
export async function clearInstallationIdForTests(): Promise<void> {
  await SecureStore.deleteItemAsync(ADMIN_INSTALLATION_ID_KEY);
}
