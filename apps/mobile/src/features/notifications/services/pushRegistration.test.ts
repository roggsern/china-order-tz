/* eslint-disable import/first -- jest.mock must hoist before imports under test */
const mockGetPermissionsAsync = jest.fn();
const mockRequestPermissionsAsync = jest.fn();
const mockGetExpoPushTokenAsync = jest.fn();
const mockSetNotificationChannelAsync = jest.fn();
const mockSetNotificationHandler = jest.fn();

jest.mock('expo-notifications', () => ({
  getPermissionsAsync: (...args: unknown[]) => mockGetPermissionsAsync(...args),
  requestPermissionsAsync: (...args: unknown[]) =>
    mockRequestPermissionsAsync(...args),
  getExpoPushTokenAsync: (...args: unknown[]) =>
    mockGetExpoPushTokenAsync(...args),
  setNotificationChannelAsync: (...args: unknown[]) =>
    mockSetNotificationChannelAsync(...args),
  setNotificationHandler: (...args: unknown[]) =>
    mockSetNotificationHandler(...args),
  AndroidImportance: { HIGH: 4 },
  IosAuthorizationStatus: { PROVISIONAL: 2 },
}));

jest.mock('expo-constants', () => ({
  __esModule: true,
  default: {
    expoConfig: {
      version: '0.1.0',
      extra: { eas: { projectId: 'c6ec7c64-8518-4e96-8df5-ac5d5a88f545' } },
    },
    deviceName: 'Test Device',
    nativeAppVersion: '0.1.0',
  },
}));

const mockRegisterDevicePushToken = jest.fn();
const mockGetOrCreateInstallationId = jest.fn();

jest.mock('@/src/features/devices', () => ({
  getOrCreateInstallationId: (...args: unknown[]) =>
    mockGetOrCreateInstallationId(...args),
  registerDevicePushToken: (...args: unknown[]) =>
    mockRegisterDevicePushToken(...args),
}));

import { Platform } from 'react-native';
import {
  registerPushForCurrentUser,
  resetPushRegistrationState,
} from './pushRegistration';

describe('registerPushForCurrentUser', () => {
  const originalOs = Platform.OS;

  beforeEach(() => {
    resetPushRegistrationState();
    mockGetPermissionsAsync.mockReset();
    mockRequestPermissionsAsync.mockReset();
    mockGetExpoPushTokenAsync.mockReset();
    mockSetNotificationChannelAsync.mockReset();
    mockRegisterDevicePushToken.mockReset();
    mockGetOrCreateInstallationId.mockReset();
    mockGetOrCreateInstallationId.mockResolvedValue(
      '11111111-1111-4111-8111-111111111111',
    );
    Object.defineProperty(Platform, 'OS', { configurable: true, value: 'android' });
  });

  afterAll(() => {
    Object.defineProperty(Platform, 'OS', { configurable: true, value: originalOs });
  });

  it('does not register when permission denied', async () => {
    mockGetPermissionsAsync.mockResolvedValue({
      granted: false,
      canAskAgain: false,
      status: 'denied',
    });

    const result = await registerPushForCurrentUser();
    expect(result).toEqual({ status: 'permission_denied' });
    expect(mockGetExpoPushTokenAsync).not.toHaveBeenCalled();
    expect(mockRegisterDevicePushToken).not.toHaveBeenCalled();
  });

  it('registers when permission granted and token obtained', async () => {
    mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });
    mockGetExpoPushTokenAsync.mockResolvedValue({
      data: 'ExponentPushToken[abc123]',
    });
    mockRegisterDevicePushToken.mockResolvedValue({
      id: 'tok-1',
      installationId: '11111111-1111-4111-8111-111111111111',
      isActive: true,
    });

    const result = await registerPushForCurrentUser();
    expect(result).toEqual({
      status: 'registered',
      token: 'ExponentPushToken[abc123]',
    });
    expect(mockRegisterDevicePushToken).toHaveBeenCalledWith(
      expect.objectContaining({
        pushToken: 'ExponentPushToken[abc123]',
        provider: 'expo',
        platform: 'android',
        installationId: '11111111-1111-4111-8111-111111111111',
      }),
    );
  });

  it('survives push token acquisition failure without crashing', async () => {
    mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });
    mockGetExpoPushTokenAsync.mockRejectedValue(new Error('Simulator not supported'));

    const result = await registerPushForCurrentUser();
    expect(result.status).toBe('unsupported_environment');
    expect(mockRegisterDevicePushToken).not.toHaveBeenCalled();
  });

  it('does not fake-register on unsupported/empty token', async () => {
    mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });
    mockGetExpoPushTokenAsync.mockResolvedValue({ data: '' });

    const result = await registerPushForCurrentUser();
    expect(result.status).toBe('token_unavailable');
    expect(mockRegisterDevicePushToken).not.toHaveBeenCalled();
  });

  it('reuses installation id across registrations', async () => {
    mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });
    mockGetExpoPushTokenAsync.mockResolvedValue({
      data: 'ExponentPushToken[abc123]',
    });
    mockRegisterDevicePushToken.mockResolvedValue({ id: 'tok-1' });

    await registerPushForCurrentUser();
    resetPushRegistrationState();
    await registerPushForCurrentUser();

    expect(mockGetOrCreateInstallationId).toHaveBeenCalledTimes(2);
    expect(mockRegisterDevicePushToken.mock.calls[0][0].installationId).toBe(
      mockRegisterDevicePushToken.mock.calls[1][0].installationId,
    );
  });
});
