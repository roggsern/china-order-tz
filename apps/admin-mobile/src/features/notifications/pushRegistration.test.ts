/* eslint-disable import/first -- jest.mock must hoist before imports under test */
const mockGetPermissionsAsync = jest.fn();
const mockRequestPermissionsAsync = jest.fn();
const mockGetExpoPushTokenAsync = jest.fn();
const mockSetNotificationChannelAsync = jest.fn();

jest.mock('expo-notifications', () => ({
  getPermissionsAsync: (...args: unknown[]) => mockGetPermissionsAsync(...args),
  requestPermissionsAsync: (...args: unknown[]) => mockRequestPermissionsAsync(...args),
  getExpoPushTokenAsync: (...args: unknown[]) => mockGetExpoPushTokenAsync(...args),
  setNotificationChannelAsync: (...args: unknown[]) => mockSetNotificationChannelAsync(...args),
  setNotificationHandler: jest.fn(),
  AndroidImportance: { HIGH: 4 },
  IosAuthorizationStatus: { PROVISIONAL: 2 },
}));

const mockExpoConfig: {
  version: string;
  extra: { eas?: { projectId?: string } };
} = {
  version: '0.1.0',
  extra: {},
};

jest.mock('expo-constants', () => ({
  __esModule: true,
  get default() {
    return {
      expoConfig: mockExpoConfig,
      deviceName: 'Test Device',
      nativeAppVersion: '0.1.0',
    };
  },
}));

const mockRegisterAdminPushToken = jest.fn();
const mockGetOrCreateInstallationId = jest.fn();

jest.mock('./adminPushApi', () => ({
  registerAdminPushToken: (...args: unknown[]) => mockRegisterAdminPushToken(...args),
  deactivateAdminPushToken: jest.fn(),
}));

jest.mock('./installationIdStorage', () => ({
  getOrCreateInstallationId: (...args: unknown[]) => mockGetOrCreateInstallationId(...args),
}));

import { Platform } from 'react-native';
import { ApiError } from '@/src/core/api';
import {
  handleAdminPushTokenRotation,
  registerAdminPush,
  resetPushRegistrationState,
  resolveExpoProjectId,
} from './pushRegistration';

const PROJECT_ID = 'c6ec7c64-8518-4e96-8df5-ac5d5a88f545';

function grantPermissionAndToken(token = 'ExponentPushToken[abc123]') {
  mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });
  mockGetExpoPushTokenAsync.mockResolvedValue({ data: token });
  mockRegisterAdminPushToken.mockResolvedValue({
    id: 'tok-1',
    installationId: '11111111-1111-4111-8111-111111111111',
    isActive: true,
  });
}

function withProjectId(): void {
  mockExpoConfig.extra = { eas: { projectId: PROJECT_ID } };
}

describe('resolveExpoProjectId', () => {
  it('returns null when project id is missing', () => {
    mockExpoConfig.extra = {};
    expect(resolveExpoProjectId()).toBeNull();
  });

  it('reads project id from expo config extra', () => {
    withProjectId();
    expect(resolveExpoProjectId()).toBe(PROJECT_ID);
  });
});

describe('registerAdminPush', () => {
  const originalOs = Platform.OS;

  beforeEach(() => {
    resetPushRegistrationState();
    mockExpoConfig.extra = {};
    mockGetPermissionsAsync.mockReset();
    mockRequestPermissionsAsync.mockReset();
    mockGetExpoPushTokenAsync.mockReset();
    mockSetNotificationChannelAsync.mockReset();
    mockRegisterAdminPushToken.mockReset();
    mockGetOrCreateInstallationId.mockReset();
    mockGetOrCreateInstallationId.mockResolvedValue('11111111-1111-4111-8111-111111111111');
    Object.defineProperty(Platform, 'OS', { configurable: true, value: 'android' });
  });

  afterAll(() => {
    Object.defineProperty(Platform, 'OS', { configurable: true, value: originalOs });
  });

  it('returns unsupported_environment when project id missing', async () => {
    mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });

    const result = await registerAdminPush({ adminId: 'admin-1' });
    expect(result).toEqual({
      status: 'unsupported_environment',
      reason: 'missing_eas_project_id',
    });
    expect(mockGetExpoPushTokenAsync).not.toHaveBeenCalled();
  });

  it('does not register when permission denied', async () => {
    withProjectId();
    mockGetPermissionsAsync.mockResolvedValue({
      granted: false,
      canAskAgain: false,
      status: 'denied',
    });

    const result = await registerAdminPush();
    expect(result).toEqual({ status: 'permission_denied' });
    expect(mockGetExpoPushTokenAsync).not.toHaveBeenCalled();
    expect(mockRegisterAdminPushToken).not.toHaveBeenCalled();
  });

  it('registers when permission granted and token obtained', async () => {
    withProjectId();
    grantPermissionAndToken();

    const result = await registerAdminPush({ adminId: 'admin-1' });
    expect(result).toEqual({
      status: 'registered',
      token: 'ExponentPushToken[abc123]',
    });
    expect(mockRegisterAdminPushToken).toHaveBeenCalledTimes(1);
  });

  it('429 does not tight-loop register calls', async () => {
    withProjectId();
    grantPermissionAndToken();
    mockRegisterAdminPushToken.mockRejectedValue(
      new ApiError({
        message: 'Too Many Requests',
        status: 429,
        code: 'rate_limited',
      }),
    );

    await registerAdminPush({ adminId: 'admin-1' });
    const second = await registerAdminPush({ adminId: 'admin-1' });
    expect(mockRegisterAdminPushToken).toHaveBeenCalledTimes(1);
    expect(second).toMatchObject({
      status: 'registration_failed',
      reason: 'backoff_active',
    });
  });

  it('token rotation with new Expo token registers once more', async () => {
    withProjectId();
    grantPermissionAndToken('ExponentPushToken[abc123]');
    await registerAdminPush({ adminId: 'admin-1' });

    mockGetExpoPushTokenAsync.mockResolvedValue({
      data: 'ExponentPushToken[rotated]',
    });
    await handleAdminPushTokenRotation('native-fcm-token', 'admin-1');
    expect(mockRegisterAdminPushToken).toHaveBeenCalledTimes(2);
  });
});
