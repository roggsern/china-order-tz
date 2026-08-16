/* eslint-disable import/first -- jest.mock must hoist before imports under test */
const mockLogoutAdmin = jest.fn();
const mockDeactivateAdminPushBestEffort = jest.fn();
const mockResetPushRegistrationState = jest.fn();
const mockGetOrCreateInstallationId = jest.fn();

jest.mock('@/src/features/auth/api/adminAuthApi', () => ({
  logoutAdmin: (...args: unknown[]) => mockLogoutAdmin(...args),
  loginAdmin: jest.fn(),
  fetchCurrentAdmin: jest.fn(),
}));

jest.mock('@/src/features/notifications', () => ({
  deactivateAdminPushBestEffort: (...args: unknown[]) => mockDeactivateAdminPushBestEffort(...args),
  resetPushRegistrationState: (...args: unknown[]) => mockResetPushRegistrationState(...args),
  getOrCreateInstallationId: (...args: unknown[]) => mockGetOrCreateInstallationId(...args),
}));

jest.mock('@/src/core/storage', () => ({
  secureTokenStorage: {
    clearToken: jest.fn(),
    readToken: jest.fn(),
    saveToken: jest.fn(),
  },
}));

import { logout } from './session';
import { useAdminAuthStore } from './adminAuthStore';
import { secureTokenStorage } from '@/src/core/storage';

describe('session logout', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetOrCreateInstallationId.mockResolvedValue('11111111-1111-4111-8111-111111111111');
    mockLogoutAdmin.mockResolvedValue(undefined);
    mockDeactivateAdminPushBestEffort.mockResolvedValue(undefined);
    useAdminAuthStore.setState({
      status: 'authenticated',
      admin: {
        id: 'a1',
        name: 'Admin',
        email: 'admin@test.com',
        phone: null,
        is_super_admin: false,
        is_active: true,
        permissions: [],
        role: null,
      },
      bootstrapStatus: 'ready',
    });
  });

  it('passes installation_id to logout and detaches push', async () => {
    await logout();

    expect(mockLogoutAdmin).toHaveBeenCalledWith({
      installation_id: '11111111-1111-4111-8111-111111111111',
    });
    expect(mockDeactivateAdminPushBestEffort).toHaveBeenCalled();
    expect(mockResetPushRegistrationState).toHaveBeenCalled();
    expect(secureTokenStorage.clearToken).toHaveBeenCalled();
    expect(useAdminAuthStore.getState().status).toBe('unauthenticated');
  });
});
