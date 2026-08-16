import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import { ApiError } from '@/src/core/api';

import { deactivateAdminPushToken, registerAdminPushToken, type AdminPushTokenPlatform } from './adminPushApi';
import { getOrCreateInstallationId } from './installationIdStorage';

export const ADMIN_OPS_CHANNEL_ID = 'admin_ops';

export type AdminPushRegistrationResult =
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
let registrationInFlight: Promise<AdminPushRegistrationResult> | null = null;
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

export async function ensureAndroidAdminOpsChannel(): Promise<void> {
  if (Platform.OS !== 'android') return;

  await Notifications.setNotificationChannelAsync(ADMIN_OPS_CHANNEL_ID, {
    name: 'Admin operations',
    description: 'Operational alerts for orders, support, and inventory',
    importance: Notifications.AndroidImportance.HIGH,
    vibrationPattern: [0, 250, 250, 250],
    lightColor: '#c9a227',
    sound: 'default',
  });
}

export function resolveExpoProjectId(): string | null {
  const fromExtra = Constants.expoConfig?.extra?.eas as { projectId?: unknown } | undefined;
  if (typeof fromExtra?.projectId === 'string' && fromExtra.projectId.trim()) {
    return fromExtra.projectId.trim();
  }
  const fromEas = (Constants as { easConfig?: { projectId?: string } }).easConfig?.projectId;
  if (typeof fromEas === 'string' && fromEas.trim()) {
    return fromEas.trim();
  }
  return null;
}

function resolvePlatform(): AdminPushTokenPlatform {
  return Platform.OS === 'ios' ? 'ios' : 'android';
}

function buildCacheKey(adminId: string, installationId: string, token: string): RegistrationCacheKey {
  return `${adminId}::${installationId}::${token}`;
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function classifyRegisterFailure(error: unknown): { reason: string; retryable: boolean } {
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
  return { reason: message, retryable: true };
}

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

export type RegisterAdminPushOptions = {
  adminId?: string | null;
  force?: boolean;
};

export async function registerAdminPush(
  options: RegisterAdminPushOptions = {},
): Promise<AdminPushRegistrationResult> {
  if (registrationInFlight) {
    return registrationInFlight;
  }

  registrationInFlight = (async (): Promise<AdminPushRegistrationResult> => {
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

      await ensureAndroidAdminOpsChannel();

      const permitted = await requestNotificationPermission();
      if (!permitted) {
        return { status: 'permission_denied' };
      }

      const projectId = resolveExpoProjectId();
      if (!projectId) {
        return {
          status: 'unsupported_environment',
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
      const adminId = (options.adminId ?? 'authenticated').trim() || 'authenticated';
      const cacheKey = buildCacheKey(adminId, installationId, token);

      if (!options.force && lastRegisteredKey === cacheKey && lastRegisteredToken === token) {
        return { status: 'registered', token };
      }

      const appVersion =
        Constants.expoConfig?.version ?? Constants.nativeAppVersion ?? null;
      const deviceName =
        typeof Constants.deviceName === 'string' && Constants.deviceName.trim()
          ? Constants.deviceName.trim()
          : null;

      let attempt = 0;
      while (attempt <= MAX_TRANSIENT_RETRIES) {
        try {
          await registerAdminPushToken({
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

export async function handleAdminPushTokenRotation(
  _nativeToken?: string | null,
  adminId?: string | null,
): Promise<AdminPushRegistrationResult> {
  return registerAdminPush({ adminId, force: false });
}

export async function deactivateAdminPushBestEffort(): Promise<void> {
  try {
    const installationId = await getOrCreateInstallationId();
    const pushToken = getLastRegisteredPushToken();
    await deactivateAdminPushToken({
      installationId,
      pushToken: pushToken ?? undefined,
    });
  } catch {
    // Best-effort detach on logout — local session must still clear.
  }
}
