import { apiClient } from '@/src/core/api';

export type AdminPushTokenProvider = 'expo';
export type AdminPushTokenPlatform = 'android' | 'ios';

export type RegisterAdminPushTokenInput = {
  pushToken: string;
  provider: AdminPushTokenProvider;
  platform: AdminPushTokenPlatform;
  installationId: string;
  appVersion?: string | null;
  deviceName?: string | null;
};

export type AdminPushTokenRegistration = {
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

export function mapAdminPushTokenRegistration(raw: unknown): AdminPushTokenRegistration | null {
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

export async function registerAdminPushToken(
  input: RegisterAdminPushTokenInput,
): Promise<AdminPushTokenRegistration> {
  const response = await apiClient.post<unknown>('/admin/devices/push-tokens', {
    push_token: input.pushToken,
    provider: input.provider,
    platform: input.platform,
    installation_id: input.installationId,
    app_version: input.appVersion ?? undefined,
    device_name: input.deviceName ?? undefined,
  });

  const mapped = mapAdminPushTokenRegistration(response.data);
  if (!mapped) {
    throw new Error('Unexpected admin push token registration response');
  }
  return mapped;
}

export async function deactivateAdminPushToken(input: {
  installationId?: string;
  pushToken?: string;
}): Promise<number> {
  const response = await apiClient.delete<{ deactivated?: number }>(
    '/admin/devices/push-tokens',
    {
      installation_id: input.installationId,
      push_token: input.pushToken,
    },
  );
  const data = asRecord(response.data);
  return typeof data.deactivated === 'number' ? data.deactivated : 0;
}
