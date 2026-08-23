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
const mockDeactivateDevicePushToken = jest.fn();
const mockGetOrCreateInstallationId = jest.fn();

jest.mock('@/src/features/devices', () => ({
  getOrCreateInstallationId: (...args: unknown[]) =>
    mockGetOrCreateInstallationId(...args),
  registerDevicePushToken: (...args: unknown[]) =>
    mockRegisterDevicePushToken(...args),
  deactivateDevicePushToken: (...args: unknown[]) =>
    mockDeactivateDevicePushToken(...args),
}));

import { Platform } from 'react-native';
import { ApiError } from '@/src/core/errors';
import {
  classifyNotificationPermission,
  deactivatePushOnLogout,
  handleExpoPushTokenRotation,
  registerPushForCurrentUser,
  resetPushRegistrationState,
} from './pushRegistration';

function grantPermissionAndToken(token = 'ExponentPushToken[abc123]') {
  mockGetPermissionsAsync.mockResolvedValue({ granted: true, status: 'granted' });
  mockGetExpoPushTokenAsync.mockResolvedValue({ data: token });
  mockRegisterDevicePushToken.mockResolvedValue({
    id: 'tok-1',
    installationId: '11111111-1111-4111-8111-111111111111',
    isActive: true,
  });
}

describe('registerPushForCurrentUser', () => {
  const originalOs = Platform.OS;

  beforeEach(() => {
    resetPushRegistrationState();
    mockGetPermissionsAsync.mockReset();
    mockRequestPermissionsAsync.mockReset();
    mockGetExpoPushTokenAsync.mockReset();
    mockSetNotificationChannelAsync.mockReset();
    mockRegisterDevicePushToken.mockReset();
    mockDeactivateDevicePushToken.mockReset();
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
    grantPermissionAndToken();

    const result = await registerPushForCurrentUser({ userId: 'user-1' });
    expect(result).toEqual({
      status: 'registered',
      token: 'ExponentPushToken[abc123]',
    });
    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(1);
  });

  it('rerender / repeat call for same tuple does not hit API again', async () => {
    grantPermissionAndToken();
    await registerPushForCurrentUser({ userId: 'user-1' });
    await registerPushForCurrentUser({ userId: 'user-1' });
    await registerPushForCurrentUser({ userId: 'user-1' });
    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(1);
  });

  it('coalesces concurrent in-flight registrations to one API call', async () => {
    grantPermissionAndToken();
    mockRegisterDevicePushToken.mockImplementation(async () => {
      await new Promise((r) => setTimeout(r, 40));
      return {
        id: 'tok-1',
        installationId: '11111111-1111-4111-8111-111111111111',
      };
    });

    const [first, second] = await Promise.all([
      registerPushForCurrentUser({ userId: 'user-1' }),
      registerPushForCurrentUser({ userId: 'user-1' }),
    ]);

    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(1);
    expect(first).toEqual(second);
    expect(first.status).toBe('registered');
  });

  it('429 does not tight-loop register calls', async () => {
    grantPermissionAndToken();
    mockRegisterDevicePushToken.mockRejectedValue(
      new ApiError({
        message: 'Too Many Requests',
        status: 429,
        code: 'rate_limited',
      }),
    );

    await registerPushForCurrentUser({ userId: 'user-1' });
    const second = await registerPushForCurrentUser({ userId: 'user-1' });
    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(1);
    expect(second).toMatchObject({
      status: 'registration_failed',
      reason: 'backoff_active',
    });
  });

  it('500 retries are bounded then backoff', async () => {
    grantPermissionAndToken();
    mockRegisterDevicePushToken.mockRejectedValue(
      new ApiError({
        message: 'Server Error',
        status: 500,
        code: 'server_error',
      }),
    );

    const result = await registerPushForCurrentUser({ userId: 'user-1' });
    // initial + MAX_TRANSIENT_RETRIES (3) = 4 attempts
    expect(mockRegisterDevicePushToken.mock.calls.length).toBeLessThanOrEqual(4);
    expect(result.status).toBe('registration_failed');
    expect(result).toMatchObject({ retryable: true });

    const blocked = await registerPushForCurrentUser({ userId: 'user-1' });
    expect(blocked).toMatchObject({ reason: 'backoff_active' });
  }, 15000);

  it('token rotation with new Expo token registers once more', async () => {
    grantPermissionAndToken('ExponentPushToken[abc123]');
    await registerPushForCurrentUser({ userId: 'user-1' });

    mockGetExpoPushTokenAsync.mockResolvedValue({
      data: 'ExponentPushToken[rotated]',
    });
    await handleExpoPushTokenRotation('native-fcm-token', 'user-1');
    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(2);
    expect(mockRegisterDevicePushToken.mock.calls[1][0].pushToken).toBe(
      'ExponentPushToken[rotated]',
    );
  });

  it('account switch registers once for new user', async () => {
    grantPermissionAndToken();
    await registerPushForCurrentUser({ userId: 'user-a' });
    resetPushRegistrationState();
    await registerPushForCurrentUser({ userId: 'user-b' });
    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(2);
  });

  it('native token listener with same Expo token does not re-register', async () => {
    grantPermissionAndToken();
    await registerPushForCurrentUser({ userId: 'user-1' });
    await handleExpoPushTokenRotation('native-fcm-token', 'user-1');
    expect(mockRegisterDevicePushToken).toHaveBeenCalledTimes(1);
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
    grantPermissionAndToken();

    await registerPushForCurrentUser({ userId: 'user-1' });
    resetPushRegistrationState();
    await registerPushForCurrentUser({ userId: 'user-1' });

    expect(mockGetOrCreateInstallationId).toHaveBeenCalledTimes(2);
    expect(mockRegisterDevicePushToken.mock.calls[0][0].installationId).toBe(
      mockRegisterDevicePushToken.mock.calls[1][0].installationId,
    );
  });

  it('does not re-prompt after a prior denial', async () => {
    mockGetPermissionsAsync.mockResolvedValue({
      granted: false,
      canAskAgain: true,
      status: 'denied',
    });

    const result = await registerPushForCurrentUser();
    expect(result).toEqual({ status: 'permission_denied' });
    expect(mockRequestPermissionsAsync).not.toHaveBeenCalled();
    expect(mockRegisterDevicePushToken).not.toHaveBeenCalled();
  });
});

describe('classifyNotificationPermission', () => {
  it('classifies granted, denied, and permanently denied without crashing', () => {
    expect(classifyNotificationPermission({ granted: true, status: 'granted' })).toBe(
      'granted',
    );
    expect(
      classifyNotificationPermission({
        granted: false,
        canAskAgain: true,
        status: 'denied',
      }),
    ).toBe('denied');
    expect(
      classifyNotificationPermission({
        granted: false,
        canAskAgain: false,
        status: 'denied',
      }),
    ).toBe('permanently_denied');
    expect(
      classifyNotificationPermission({
        granted: false,
        status: 'undetermined',
      }),
    ).toBe('undetermined');
  });
});

describe('deactivatePushOnLogout', () => {
  const originalOs = Platform.OS;

  beforeEach(() => {
    resetPushRegistrationState();
    mockDeactivateDevicePushToken.mockReset();
    mockGetOrCreateInstallationId.mockReset();
    mockGetOrCreateInstallationId.mockResolvedValue(
      '11111111-1111-4111-8111-111111111111',
    );
    Object.defineProperty(Platform, 'OS', { configurable: true, value: 'android' });
  });

  afterAll(() => {
    Object.defineProperty(Platform, 'OS', { configurable: true, value: originalOs });
  });

  it('calls DELETE /devices/push-tokens for the current installation', async () => {
    grantPermissionAndToken();
    await registerPushForCurrentUser({ userId: 'user-1' });
    mockDeactivateDevicePushToken.mockResolvedValue(1);

    const result = await deactivatePushOnLogout();

    expect(mockDeactivateDevicePushToken).toHaveBeenCalledWith({
      installationId: '11111111-1111-4111-8111-111111111111',
      pushToken: 'ExponentPushToken[abc123]',
    });
    expect(result).toEqual({
      deactivated: true,
      installationId: '11111111-1111-4111-8111-111111111111',
      hadPushToken: true,
    });
  });

  it('handles deactivate failure without throwing', async () => {
    mockDeactivateDevicePushToken.mockRejectedValue(new Error('offline'));

    await expect(deactivatePushOnLogout()).resolves.toEqual({
      deactivated: false,
      installationId: '11111111-1111-4111-8111-111111111111',
      hadPushToken: false,
    });
  });
});
