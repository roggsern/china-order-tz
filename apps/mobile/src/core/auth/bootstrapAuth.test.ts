import { ApiError } from '@/src/core/errors';
import { bootstrapAuth } from '@/src/core/auth/bootstrapAuth';
import { useAuthStore } from '@/src/core/auth/authStore';
import { apiClient } from '@/src/core/api';
import { secureTokenStorage } from '@/src/core/storage';

const mockClearSessionImpl = jest.fn(async () => {
  await secureTokenStorage.clearToken();
  useAuthStore.getState().setUnauthenticated();
});

jest.mock('@/src/core/storage', () => ({
  secureTokenStorage: {
    readToken: jest.fn(),
    clearToken: jest.fn(),
    saveToken: jest.fn(),
  },
}));

jest.mock('@/src/core/api', () => ({
  apiClient: {
    get: jest.fn(),
  },
}));

jest.mock('@/src/core/auth/clearSession', () => ({
  get clearSession() {
    return mockClearSessionImpl;
  },
  get clearSessionOnAuthFailure() {
    return mockClearSessionImpl;
  },
  get clearSessionOnLogout() {
    return mockClearSessionImpl;
  },
}));

const mockReadToken = secureTokenStorage.readToken as jest.Mock;
const mockClearToken = secureTokenStorage.clearToken as jest.Mock;
const mockApiGet = apiClient.get as jest.Mock;

const validUser = {
  id: 'usr_1',
  name: 'Ada',
  email: 'ada@example.com',
};

describe('bootstrapAuth', () => {
  beforeEach(() => {
    mockReadToken.mockReset();
    mockClearToken.mockReset();
    mockApiGet.mockReset();
    mockClearSessionImpl.mockClear();
    useAuthStore.setState({
      status: 'unknown',
      user: null,
      bootstrapStatus: 'pending',
    });
  });

  it('bootstrap unauthenticated when no token is stored', async () => {
    mockReadToken.mockResolvedValue(null);

    const result = await bootstrapAuth();

    expect(result).toEqual({ status: 'unauthenticated', reason: 'no_token' });
    expect(mockApiGet).not.toHaveBeenCalled();
    expect(useAuthStore.getState().status).toBe('unauthenticated');
    expect(useAuthStore.getState().bootstrapStatus).toBe('complete');
  });

  it('keeps GET /me phone on the authenticated user', async () => {
    mockReadToken.mockResolvedValue('tok_abc');
    mockApiGet.mockResolvedValue({
      success: true,
      data: { ...validUser, phone: '+255712345678' },
    });

    const result = await bootstrapAuth();

    expect(result).toEqual({
      status: 'authenticated',
      user: { ...validUser, phone: '+255712345678' },
    });
    expect(useAuthStore.getState().user?.phone).toBe('+255712345678');
  });

  it('bootstrap authenticated when GET /me succeeds', async () => {
    mockReadToken.mockResolvedValue('tok_abc');
    mockApiGet.mockResolvedValue({ success: true, data: validUser });

    const result = await bootstrapAuth();

    expect(mockApiGet).toHaveBeenCalledWith('/me', undefined, 'tok_abc');
    expect(result).toEqual({ status: 'authenticated', user: validUser });
    expect(useAuthStore.getState().status).toBe('authenticated');
    expect(useAuthStore.getState().bootstrapStatus).toBe('complete');
    expect(mockClearSessionImpl).not.toHaveBeenCalled();
  });

  it('clears session (token + auth) when API reports unauthenticated', async () => {
    mockReadToken.mockResolvedValue('tok_stale');
    mockApiGet.mockRejectedValue(
      new ApiError({
        message: 'Unauthenticated',
        status: 401,
        code: 'unauthenticated',
      }),
    );

    const result = await bootstrapAuth();

    expect(result).toEqual({
      status: 'unauthenticated',
      reason: 'unauthenticated',
    });
    expect(mockClearSessionImpl).toHaveBeenCalled();
    expect(mockClearToken).toHaveBeenCalled();
    expect(useAuthStore.getState().status).toBe('unauthenticated');
  });

  it('keeps token on network error but routes as unauthenticated', async () => {
    mockReadToken.mockResolvedValue('tok_keep');
    mockApiGet.mockRejectedValue(
      new ApiError({
        message: 'Network request failed',
        status: 0,
        code: 'server_error',
      }),
    );

    const result = await bootstrapAuth();

    expect(result).toEqual({ status: 'unauthenticated', reason: 'error' });
    expect(mockClearSessionImpl).not.toHaveBeenCalled();
    expect(mockClearToken).not.toHaveBeenCalled();
  });
});
