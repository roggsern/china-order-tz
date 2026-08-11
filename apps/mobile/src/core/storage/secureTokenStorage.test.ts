import * as SecureStore from 'expo-secure-store';
import { secureTokenStorage } from '@/src/core/storage/secureTokenStorage';

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(),
  getItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

const mockSetItemAsync = SecureStore.setItemAsync as jest.Mock;
const mockGetItemAsync = SecureStore.getItemAsync as jest.Mock;
const mockDeleteItemAsync = SecureStore.deleteItemAsync as jest.Mock;

describe('secureTokenStorage', () => {
  beforeEach(() => {
    mockSetItemAsync.mockReset();
    mockGetItemAsync.mockReset();
    mockDeleteItemAsync.mockReset();
  });

  it('saves trimmed tokens to SecureStore', async () => {
    await secureTokenStorage.saveToken('  abc.token  ');
    expect(mockSetItemAsync).toHaveBeenCalledWith('cotz.auth.access_token', 'abc.token');
  });

  it('clears when saving blank token', async () => {
    await secureTokenStorage.saveToken('   ');
    expect(mockDeleteItemAsync).toHaveBeenCalledWith('cotz.auth.access_token');
    expect(mockSetItemAsync).not.toHaveBeenCalled();
  });

  it('reads null for missing or blank values', async () => {
    mockGetItemAsync.mockResolvedValueOnce(null);
    await expect(secureTokenStorage.readToken()).resolves.toBeNull();

    mockGetItemAsync.mockResolvedValueOnce('  ');
    await expect(secureTokenStorage.readToken()).resolves.toBeNull();
  });

  it('clears token via SecureStore delete', async () => {
    await secureTokenStorage.clearToken();
    expect(mockDeleteItemAsync).toHaveBeenCalledWith('cotz.auth.access_token');
  });
});
