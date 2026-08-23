import { apiClient } from '@/src/core/api';
import {
  deactivateDevicePushToken,
  mapDevicePushTokenRegistration,
  type RegisterDevicePushTokenInput,
} from './devicePushTokensApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    delete: jest.fn(),
  },
}));

const mockDelete = apiClient.delete as jest.MockedFunction<typeof apiClient.delete>;

describe('devicePushTokensApi', () => {
  beforeEach(() => {
    mockDelete.mockReset();
  });

  it('maps sanitized registration resource without requiring push_token in response', () => {
    const mapped = mapDevicePushTokenRegistration({
      id: 'tok-1',
      provider: 'expo',
      platform: 'android',
      installation_id: '11111111-1111-4111-8111-111111111111',
      is_active: true,
      last_seen_at: '2026-08-12T00:00:00Z',
      created_at: '2026-08-12T00:00:00Z',
      updated_at: '2026-08-12T00:00:00Z',
    });

    expect(mapped).toEqual({
      id: 'tok-1',
      provider: 'expo',
      platform: 'android',
      installationId: '11111111-1111-4111-8111-111111111111',
      isActive: true,
      lastSeenAt: '2026-08-12T00:00:00Z',
      createdAt: '2026-08-12T00:00:00Z',
      updatedAt: '2026-08-12T00:00:00Z',
    });
  });

  it('rejects incomplete resources', () => {
    expect(mapDevicePushTokenRegistration({ id: 'tok-1' })).toBeNull();
  });

  it('keeps register input typed without inventing tokens', () => {
    const input: RegisterDevicePushTokenInput = {
      pushToken: 'ExponentPushToken[real-token-from-expo]',
      provider: 'expo',
      platform: 'android',
      installationId: '11111111-1111-4111-8111-111111111111',
    };
    expect(input.provider).toBe('expo');
  });

  it('deactivates via DELETE /devices/push-tokens', async () => {
    mockDelete.mockResolvedValue({ data: { deactivated: 1 } } as never);

    const deactivated = await deactivateDevicePushToken({
      installationId: '11111111-1111-4111-8111-111111111111',
    });

    expect(mockDelete).toHaveBeenCalledWith('/devices/push-tokens', {
      installation_id: '11111111-1111-4111-8111-111111111111',
      push_token: undefined,
    });
    expect(deactivated).toBe(1);
  });
});
