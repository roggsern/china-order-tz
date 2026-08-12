import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import {
  getOrCreateInstallationId,
  registerDevicePushToken,
  type PushTokenPlatform,
} from '@/src/features/devices';

export const ORDER_UPDATES_CHANNEL_ID = 'order_updates';

export type PushRegistrationResult =
  | { status: 'registered'; token: string }
  | { status: 'permission_denied' }
  | { status: 'unsupported_environment'; reason: string }
  | { status: 'token_unavailable'; reason: string }
  | { status: 'registration_failed'; reason: string };

let lastRegisteredToken: string | null = null;
let registrationInFlight: Promise<PushRegistrationResult> | null = null;

export function getLastRegisteredPushToken(): string | null {
  return lastRegisteredToken;
}

export function resetPushRegistrationState(): void {
  lastRegisteredToken = null;
  registrationInFlight = null;
}

/** @deprecated Prefer resetPushRegistrationState */
export function resetPushRegistrationStateForTests(): void {
  resetPushRegistrationState();
}

/**
 * Foreground presentation — show banner/list/sound; never auto-navigate.
 */
export function configureForegroundNotificationHandler(): void {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });
}

export async function ensureAndroidOrderUpdatesChannel(): Promise<void> {
  if (Platform.OS !== 'android') return;

  await Notifications.setNotificationChannelAsync(ORDER_UPDATES_CHANNEL_ID, {
    name: 'Order updates',
    description: 'Transactional order, payment, shipment, and support alerts',
    importance: Notifications.AndroidImportance.HIGH,
    vibrationPattern: [0, 250, 250, 250],
    lightColor: '#c9a227',
    sound: 'default',
  });
}

function resolveExpoProjectId(): string | null {
  const fromExtra = Constants.expoConfig?.extra?.eas as
    | { projectId?: unknown }
    | undefined;
  if (typeof fromExtra?.projectId === 'string' && fromExtra.projectId.trim()) {
    return fromExtra.projectId.trim();
  }
  const fromEas = (Constants as { easConfig?: { projectId?: string } }).easConfig
    ?.projectId;
  if (typeof fromEas === 'string' && fromEas.trim()) {
    return fromEas.trim();
  }
  return null;
}

function resolvePlatform(): PushTokenPlatform {
  return Platform.OS === 'ios' ? 'ios' : 'android';
}

/**
 * Request permission once per call site — does not re-prompt if already decided.
 */
export async function requestNotificationPermission(): Promise<boolean> {
  const current = await Notifications.getPermissionsAsync();
  if (current.granted || current.ios?.status === Notifications.IosAuthorizationStatus.PROVISIONAL) {
    return true;
  }
  if (!current.canAskAgain && current.status === 'denied') {
    return false;
  }
  const requested = await Notifications.requestPermissionsAsync();
  return (
    requested.granted ||
    requested.ios?.status === Notifications.IosAuthorizationStatus.PROVISIONAL
  );
}

/**
 * Obtain Expo push token + register with Wave 6A API. No fake tokens.
 */
export async function registerPushForCurrentUser(): Promise<PushRegistrationResult> {
  if (registrationInFlight) {
    return registrationInFlight;
  }

  registrationInFlight = (async (): Promise<PushRegistrationResult> => {
    try {
      if (Platform.OS === 'web') {
        return {
          status: 'unsupported_environment',
          reason: 'web_push_not_configured',
        };
      }

      await ensureAndroidOrderUpdatesChannel();

      const permitted = await requestNotificationPermission();
      if (!permitted) {
        return { status: 'permission_denied' };
      }

      const projectId = resolveExpoProjectId();
      if (!projectId) {
        return {
          status: 'token_unavailable',
          reason: 'missing_eas_project_id',
        };
      }

      let token: string;
      try {
        const result = await Notifications.getExpoPushTokenAsync({ projectId });
        token = result.data?.trim() ?? '';
      } catch (error) {
        const message =
          error instanceof Error ? error.message : 'getExpoPushTokenAsync_failed';
        // Emulators / Expo Go limitations often surface here.
        return {
          status: 'unsupported_environment',
          reason: message,
        };
      }

      if (!token || !/^Expo(nent)?PushToken\[.+\]$/.test(token)) {
        return {
          status: 'token_unavailable',
          reason: 'invalid_or_empty_token',
        };
      }

      if (lastRegisteredToken === token) {
        return { status: 'registered', token };
      }

      const installationId = await getOrCreateInstallationId();
      const appVersion =
        Constants.expoConfig?.version ??
        Constants.nativeAppVersion ??
        null;
      const deviceName =
        typeof Constants.deviceName === 'string' && Constants.deviceName.trim()
          ? Constants.deviceName.trim()
          : null;

      await registerDevicePushToken({
        pushToken: token,
        provider: 'expo',
        platform: resolvePlatform(),
        installationId,
        appVersion,
        deviceName,
      });

      lastRegisteredToken = token;
      return { status: 'registered', token };
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'registration_failed';
      return { status: 'registration_failed', reason: message };
    } finally {
      registrationInFlight = null;
    }
  })();

  return registrationInFlight;
}
