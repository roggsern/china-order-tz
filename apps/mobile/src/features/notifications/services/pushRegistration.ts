import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { ApiError } from '@/src/core/errors';
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
  | { status: 'registration_failed'; reason: string; retryable: boolean };

type RegistrationCacheKey = string;

const MAX_TRANSIENT_RETRIES = 3;
const BASE_BACKOFF_MS = 750;

let lastRegisteredKey: RegistrationCacheKey | null = null;
let lastRegisteredToken: string | null = null;
let registrationInFlight: Promise<PushRegistrationResult> | null = null;
let consecutiveFailures = 0;
let nextAllowedAttemptAt = 0;

export function getLastRegisteredPushToken(): string | null {
  return lastRegisteredToken;
}

export function resetPushRegistrationState(): void {
  lastRegisteredKey = null;
  lastRegisteredToken = null;
  registrationInFlight = null;
  consecutiveFailures = 0;
  nextAllowedAttemptAt = 0;
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

function buildCacheKey(userId: string, installationId: string, token: string): RegistrationCacheKey {
  return `${userId}::${installationId}::${token}`;
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function classifyRegisterFailure(error: unknown): {
  reason: string;
  retryable: boolean;
} {
  if (error instanceof ApiError) {
    const status = error.status;
    if (status === 401 || status === 403 || status === 422) {
      return { reason: `http_${status}`, retryable: false };
    }
    if (status === 429) {
      return { reason: 'http_429', retryable: false };
    }
    if (status >= 500 && status < 600) {
      return { reason: `http_${status}`, retryable: true };
    }
    return { reason: `http_${status}`, retryable: false };
  }
  const message = error instanceof Error ? error.message : 'registration_failed';
  // Network blips may be transient; keep bounded.
  return { reason: message, retryable: true };
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

export type RegisterPushOptions = {
  userId?: string | null;
  /** Force a fresh attempt even if the same tuple was registered this session. */
  force?: boolean;
};

/**
 * Obtain Expo push token + register with Wave 6A API. No fake tokens.
 * Idempotent per (user, installation, token). Failures use bounded backoff.
 */
export async function registerPushForCurrentUser(
  options: RegisterPushOptions = {},
): Promise<PushRegistrationResult> {
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

      const now = Date.now();
      if (!options.force && now < nextAllowedAttemptAt) {
        return {
          status: 'registration_failed',
          reason: 'backoff_active',
          retryable: true,
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

      const installationId = await getOrCreateInstallationId();
      const userId = (options.userId ?? 'authenticated').trim() || 'authenticated';
      const cacheKey = buildCacheKey(userId, installationId, token);

      if (!options.force && lastRegisteredKey === cacheKey && lastRegisteredToken === token) {
        return { status: 'registered', token };
      }

      const appVersion =
        Constants.expoConfig?.version ??
        Constants.nativeAppVersion ??
        null;
      const deviceName =
        typeof Constants.deviceName === 'string' && Constants.deviceName.trim()
          ? Constants.deviceName.trim()
          : null;

      let attempt = 0;
      while (attempt <= MAX_TRANSIENT_RETRIES) {
        try {
          await registerDevicePushToken({
            pushToken: token,
            provider: 'expo',
            platform: resolvePlatform(),
            installationId,
            appVersion,
            deviceName,
          });

          lastRegisteredToken = token;
          lastRegisteredKey = cacheKey;
          consecutiveFailures = 0;
          nextAllowedAttemptAt = 0;
          return { status: 'registered', token };
        } catch (error) {
          const classified = classifyRegisterFailure(error);
          if (!classified.retryable || attempt === MAX_TRANSIENT_RETRIES) {
            consecutiveFailures += 1;
            // Hard stop tight loops: 4xx/429 or exhausted 5xx retries.
            const backoffMs = classified.retryable
              ? BASE_BACKOFF_MS * 2 ** Math.min(consecutiveFailures, 4)
              : BASE_BACKOFF_MS * 8;
            nextAllowedAttemptAt = Date.now() + backoffMs;
            return {
              status: 'registration_failed',
              reason: classified.reason,
              retryable: classified.retryable,
            };
          }
          await sleep(BASE_BACKOFF_MS * 2 ** attempt);
          attempt += 1;
        }
      }

      return {
        status: 'registration_failed',
        reason: 'exhausted_retries',
        retryable: true,
      };
    } catch (error) {
      const classified = classifyRegisterFailure(error);
      consecutiveFailures += 1;
      nextAllowedAttemptAt =
        Date.now() + BASE_BACKOFF_MS * 2 ** Math.min(consecutiveFailures, 4);
      return {
        status: 'registration_failed',
        reason: classified.reason,
        retryable: classified.retryable,
      };
    } finally {
      registrationInFlight = null;
    }
  })();

  return registrationInFlight;
}

/**
 * Native device push token changed (FCM/APNs). Re-resolve Expo token and register
 * if the Expo token tuple changed — never clear an in-flight registration blindly.
 */
export async function handleExpoPushTokenRotation(
  _nativeToken?: string | null,
  userId?: string | null,
): Promise<PushRegistrationResult> {
  // Do not reset in-flight; registerPushForCurrentUser coalesces concurrent calls.
  // force=false still re-fetches Expo token; cache short-circuits if tuple unchanged.
  return registerPushForCurrentUser({ userId, force: false });
}
