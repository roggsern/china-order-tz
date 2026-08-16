import * as SecureStore from 'expo-secure-store';

import {
  ADMIN_INSTALLATION_ID_KEY,
  clearInstallationIdForTests,
  getOrCreateInstallationId,
} from './installationIdStorage';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

describe('installationIdStorage', () => {
  beforeEach(async () => {
    jest.clearAllMocks();
    await clearInstallationIdForTests();
  });

  it('returns existing installation id from SecureStore', async () => {
    (SecureStore.getItemAsync as jest.Mock).mockResolvedValue(
      '11111111-1111-4111-8111-111111111111',
    );

    const id = await getOrCreateInstallationId();
    expect(id).toBe('11111111-1111-4111-8111-111111111111');
    expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
  });

  it('creates and persists a new uuid when missing', async () => {
    (SecureStore.getItemAsync as jest.Mock).mockResolvedValue(null);

    const id = await getOrCreateInstallationId();
    expect(id).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
    );
    expect(SecureStore.setItemAsync).toHaveBeenCalledWith(ADMIN_INSTALLATION_ID_KEY, id);
  });

  it('reuses the same id on subsequent calls', async () => {
    let stored: string | null = null;
    (SecureStore.getItemAsync as jest.Mock).mockImplementation(async () => stored);
    (SecureStore.setItemAsync as jest.Mock).mockImplementation(async (_key, value) => {
      stored = value;
    });

    const first = await getOrCreateInstallationId();
    const second = await getOrCreateInstallationId();
    expect(second).toBe(first);
    expect(SecureStore.setItemAsync).toHaveBeenCalledTimes(1);
  });
});
