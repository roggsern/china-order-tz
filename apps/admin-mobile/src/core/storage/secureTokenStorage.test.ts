import * as SecureStore from 'expo-secure-store';

import { secureTokenStorage, TOKEN_KEY } from './secureTokenStorage';

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(),
  getItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

describe('secureTokenStorage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('persists token under admin key only', async () => {
    await secureTokenStorage.saveToken('abc123');
    expect(SecureStore.setItemAsync).toHaveBeenCalledWith(TOKEN_KEY, 'abc123');
  });

  it('clears token when saving empty string', async () => {
    await secureTokenStorage.saveToken('   ');
    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith(TOKEN_KEY);
  });

  it('reads token from SecureStore', async () => {
    (SecureStore.getItemAsync as jest.Mock).mockResolvedValue('token');
    await expect(secureTokenStorage.readToken()).resolves.toBe('token');
  });

  it('returns null for blank stored token', async () => {
    (SecureStore.getItemAsync as jest.Mock).mockResolvedValue('  ');
    await expect(secureTokenStorage.readToken()).resolves.toBeNull();
  });

  it('clears token on logout', async () => {
    await secureTokenStorage.clearToken();
    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith(TOKEN_KEY);
  });
});
