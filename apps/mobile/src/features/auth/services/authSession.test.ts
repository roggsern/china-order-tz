import { ApiError } from '@/src/core/errors';
import { useAuthStore } from '@/src/core/auth/authStore';
import { apiClient } from '@/src/core/api';
import { secureTokenStorage } from '@/src/core/storage';
import { getAuthErrorMessage } from '@/src/features/auth/utils/authErrorMessage';
import { loginWithPassword, logout } from '@/src/features/auth/services/authSession';
import { pendingPaymentContextStorage } from '@/src/features/payments/storage/pendingPaymentContextStorage';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';

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
    post: jest.fn(),
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

jest.mock('@/src/features/payments/storage/pendingPaymentContextStorage', () => ({
  pendingPaymentContextStorage: {
    bindToAuthenticatedUser: jest.fn().mockResolvedValue(null),
    clear: jest.fn(),
  },
}));

jest.mock('@/src/features/checkout/storage/pendingCheckoutContextStorage', () => ({
  pendingCheckoutContextStorage: {
    bindToAuthenticatedUser: jest.fn().mockResolvedValue(null),
    clear: jest.fn(),
  },
}));

const mockSaveToken = secureTokenStorage.saveToken as jest.Mock;
const mockClearToken = secureTokenStorage.clearToken as jest.Mock;
const mockApiPost = apiClient.post as jest.Mock;
const mockBindPayment =
  pendingPaymentContextStorage.bindToAuthenticatedUser as jest.Mock;
const mockBindCheckout =
  pendingCheckoutContextStorage.bindToAuthenticatedUser as jest.Mock;

const validUser = {
  id: 'usr_1',
  name: 'Ada',
  email: 'ada@example.com',
};

describe('loginWithPassword', () => {
  beforeEach(() => {
    mockSaveToken.mockReset();
    mockClearToken.mockReset();
    mockApiPost.mockReset();
    mockBindPayment.mockReset().mockResolvedValue(null);
    mockBindCheckout.mockReset().mockResolvedValue(null);
    useAuthStore.setState({
      status: 'unauthenticated',
      user: null,
      bootstrapStatus: 'complete',
    });
  });

  it('saves token and authenticates on success', async () => {
    mockApiPost.mockResolvedValue({
      success: true,
      message: 'Login successful',
      token: 'plain_token',
      token_type: 'Bearer',
      data: validUser,
    });

    const user = await loginWithPassword({
      email: 'ada@example.com',
      password: 'Password123!',
    });

    expect(mockApiPost).toHaveBeenCalledWith(
      '/login',
      { email: 'ada@example.com', password: 'Password123!' },
      null,
    );
    expect(mockSaveToken).toHaveBeenCalledWith('plain_token');
    expect(mockBindPayment).toHaveBeenCalledWith('usr_1');
    expect(mockBindCheckout).toHaveBeenCalledWith('usr_1');
    expect(user).toEqual(validUser);
    expect(useAuthStore.getState().status).toBe('authenticated');
    expect(useAuthStore.getState().user).toEqual(validUser);
  });

  it('maps invalid_credentials failures', async () => {
    mockApiPost.mockRejectedValue(
      new ApiError({
        message: 'Invalid credentials',
        status: 422,
        code: 'invalid_credentials',
      }),
    );

    await expect(
      loginWithPassword({ email: 'ada@example.com', password: 'bad' }),
    ).rejects.toMatchObject({ code: 'invalid_credentials' });

    expect(mockSaveToken).not.toHaveBeenCalled();
    expect(useAuthStore.getState().status).toBe('unauthenticated');
    expect(
      getAuthErrorMessage(
        new ApiError({
          message: 'Invalid credentials',
          status: 422,
          code: 'invalid_credentials',
        }),
      ),
    ).toBe('Invalid credentials');
  });

  it('maps account_disabled failures', async () => {
    const error = new ApiError({
      message: 'Account disabled',
      status: 403,
      code: 'account_disabled',
    });
    mockApiPost.mockRejectedValue(error);

    await expect(
      loginWithPassword({ email: 'ada@example.com', password: 'Password123!' }),
    ).rejects.toMatchObject({ code: 'account_disabled' });

    expect(getAuthErrorMessage(error)).toBe('Account disabled');
  });
});

describe('logout', () => {
  beforeEach(() => {
    mockClearToken.mockReset();
    mockApiPost.mockReset();
    mockClearSessionImpl.mockClear();
    useAuthStore.setState({
      status: 'authenticated',
      user: validUser,
      bootstrapStatus: 'complete',
    });
  });

  it('clears session via clearSession even when logout API fails', async () => {
    mockApiPost.mockRejectedValue(
      new ApiError({
        message: 'Unauthenticated',
        status: 401,
        code: 'unauthenticated',
      }),
    );

    await logout();

    expect(mockClearSessionImpl).toHaveBeenCalled();
    expect(mockClearToken).toHaveBeenCalled();
    expect(useAuthStore.getState().status).toBe('unauthenticated');
    expect(useAuthStore.getState().user).toBeNull();
  });
});
