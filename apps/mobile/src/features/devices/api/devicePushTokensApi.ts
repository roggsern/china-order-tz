import { apiClient } from '@/src/core/api';

export type PushTokenProvider = 'expo';
export type PushTokenPlatform = 'android' | 'ios';

export type RegisterDevicePushTokenInput = {
  pushToken: string;
  provider: PushTokenProvider;
  platform: PushTokenPlatform;
  installationId: string;
  appVersion?: string | null;
  deviceName?: string | null;
};

export type DevicePushTokenRegistration = {
  id: string;
  provider: string;
  platform: string;
  installationId: string;
  isActive: boolean;
  lastSeenAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function mapDevicePushTokenRegistration(
  raw: unknown,
): DevicePushTokenRegistration | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const installationId = stringField(data, 'installation_id');
  if (!id || !installationId) return null;

  return {
    id,
    provider: stringField(data, 'provider') ?? 'expo',
    platform: stringField(data, 'platform') ?? 'android',
    installationId,
    isActive: data.is_active === true,
    lastSeenAt: stringField(data, 'last_seen_at'),
    createdAt: stringField(data, 'created_at'),
    updatedAt: stringField(data, 'updated_at'),
  };
}

/**
 * POST /devices/push-tokens — Wave 6A client foundation.
 * Callers must supply a real Expo push token later (Wave 6D); do not invent tokens.
 */
export async function registerDevicePushToken(
  input: RegisterDevicePushTokenInput,
): Promise<DevicePushTokenRegistration> {
  const response = await apiClient.post<unknown>('/devices/push-tokens', {
    push_token: input.pushToken,
    provider: input.provider,
    platform: input.platform,
    installation_id: input.installationId,
    app_version: input.appVersion ?? undefined,
    device_name: input.deviceName ?? undefined,
  });

  const mapped = mapDevicePushTokenRegistration(response.data);
  if (!mapped) {
    throw new Error('Unexpected device push token registration response');
  }
  return mapped;
}

/**
 * DELETE /devices/push-tokens — deactivate current installation and/or token.
 */
export async function deactivateDevicePushToken(input: {
  installationId?: string;
  pushToken?: string;
}): Promise<number> {
  const response = await apiClient.delete<{ deactivated?: number }>(
    '/devices/push-tokens',
    {
      installation_id: input.installationId,
      push_token: input.pushToken,
    },
  );
  const data = asRecord(response.data);
  return typeof data.deactivated === 'number' ? data.deactivated : 0;
}
