import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'cotz.admin.access_token';

export type SecureTokenStorage = {
  saveToken: (token: string) => Promise<void>;
  readToken: () => Promise<string | null>;
  clearToken: () => Promise<void>;
};

/**
 * Admin auth token storage — SecureStore only.
 * Never persist tokens in AsyncStorage or Zustand persist middleware.
 */
export const secureTokenStorage: SecureTokenStorage = {
  async saveToken(token: string): Promise<void> {
    const trimmed = token.trim();
    if (!trimmed) {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      return;
    }
    await SecureStore.setItemAsync(TOKEN_KEY, trimmed);
  },

  async readToken(): Promise<string | null> {
    const value = await SecureStore.getItemAsync(TOKEN_KEY);
    if (value == null || value.trim() === '') {
      return null;
    }
    return value;
  },

  async clearToken(): Promise<void> {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
  },
};

export { TOKEN_KEY };
