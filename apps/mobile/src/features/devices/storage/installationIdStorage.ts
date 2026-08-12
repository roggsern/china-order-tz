import * as SecureStore from 'expo-secure-store';

const INSTALLATION_ID_KEY = 'china_order_tz.installation_id';

function createUuidV4(): string {
  if (
    typeof globalThis.crypto !== 'undefined' &&
    typeof globalThis.crypto.randomUUID === 'function'
  ) {
    return globalThis.crypto.randomUUID();
  }

  // RFC 4122-ish fallback when randomUUID is unavailable.
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;
    const value = char === 'x' ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}

/**
 * Stable per-app-install identity for future push-token registration (Wave 6A).
 * Not an auth credential. Persisted in SecureStore.
 */
export async function getOrCreateInstallationId(): Promise<string> {
  const existing = await SecureStore.getItemAsync(INSTALLATION_ID_KEY);
  if (existing && existing.trim() !== '') {
    return existing.trim().toLowerCase();
  }

  const created = createUuidV4().toLowerCase();
  await SecureStore.setItemAsync(INSTALLATION_ID_KEY, created);
  return created;
}

/** Test / teardown helper — does not clear auth tokens. */
export async function clearInstallationIdForTests(): Promise<void> {
  await SecureStore.deleteItemAsync(INSTALLATION_ID_KEY);
}
